import { WebSocketServer } from 'ws'
import * as Y from 'yjs'
import { readSyncMessage, writeSyncStep1, writeUpdate } from 'y-protocols/sync'
import { Awareness, applyAwarenessUpdate, encodeAwarenessUpdate, removeAwarenessStates } from 'y-protocols/awareness'
import * as encoding from 'lib0/encoding'
import * as decoding from 'lib0/decoding'

const MSG_SYNC = 0
const MSG_AWARENESS = 1

const port = process.env.YJS_PORT || 1234

// Map of room name -> { doc, awareness, conns }
const rooms = new Map()

function getRoom(roomName) {
  if (rooms.has(roomName)) return rooms.get(roomName)

  const doc = new Y.Doc()
  const awareness = new Awareness(doc)
  const conns = new Map() // ws -> Set<clientId>

  const room = { doc, awareness, conns }
  rooms.set(roomName, room)

  // Broadcast doc updates to all connected clients
  doc.on('update', (update, origin) => {
    const encoder = encoding.createEncoder()
    encoding.writeVarUint(encoder, MSG_SYNC)
    writeUpdate(encoder, update)
    const message = encoding.toUint8Array(encoder)
    room.conns.forEach((_meta, conn) => {
      if (conn !== origin && conn.readyState === 1) {
        try { conn.send(message) } catch (e) { /* ignore */ }
      }
    })
  })

  // Broadcast awareness changes to all connected clients
  awareness.on('update', ({ added, updated, removed }, origin) => {
    const changedClients = added.concat(updated, removed)
    if (changedClients.length === 0) return
    const encoder = encoding.createEncoder()
    encoding.writeVarUint(encoder, MSG_AWARENESS)
    encoding.writeVarUint8Array(encoder, encodeAwarenessUpdate(awareness, changedClients))
    const message = encoding.toUint8Array(encoder)
    room.conns.forEach((_meta, conn) => {
      if (conn !== origin && conn.readyState === 1) {
        try { conn.send(message) } catch (e) { /* ignore */ }
      }
    })
  })

  return room
}

const wss = new WebSocketServer({ port })

wss.on('connection', (ws, req) => {
  // Extract room name from URL path: /roomName
  const roomName = req.url?.slice(1)?.split('?')[0] || 'default'
  const room = getRoom(roomName)
  const controlledIds = new Set()
  room.conns.set(ws, controlledIds)

  // Send sync step 1
  const syncEncoder = encoding.createEncoder()
  encoding.writeVarUint(syncEncoder, MSG_SYNC)
  writeSyncStep1(syncEncoder, room.doc)
  ws.send(encoding.toUint8Array(syncEncoder))

  // Send current awareness states to the new client
  const states = room.awareness.getStates()
  if (states.size > 0) {
    const awarenessEncoder = encoding.createEncoder()
    encoding.writeVarUint(awarenessEncoder, MSG_AWARENESS)
    encoding.writeVarUint8Array(
      awarenessEncoder,
      encodeAwarenessUpdate(room.awareness, Array.from(states.keys()))
    )
    ws.send(encoding.toUint8Array(awarenessEncoder))
  }

  ws.on('message', (data) => {
    try {
      const message = new Uint8Array(data)
      const decoder = decoding.createDecoder(message)
      const messageType = decoding.readVarUint(decoder)

      switch (messageType) {
        case MSG_SYNC: {
          const responseEncoder = encoding.createEncoder()
          encoding.writeVarUint(responseEncoder, MSG_SYNC)
          readSyncMessage(decoder, responseEncoder, room.doc, ws)
          if (encoding.length(responseEncoder) > 1) {
            ws.send(encoding.toUint8Array(responseEncoder))
          }
          break
        }
        case MSG_AWARENESS: {
          // Read the awareness update as a length-prefixed byte array
          const update = decoding.readVarUint8Array(decoder)
          // Apply via y-protocols (fires 'update' event which broadcasts to others)
          applyAwarenessUpdate(room.awareness, update, ws)
          // Track which client IDs this connection controls (for cleanup on disconnect)
          try {
            const trackDecoder = decoding.createDecoder(update)
            const len = decoding.readVarUint(trackDecoder)
            for (let i = 0; i < len; i++) {
              controlledIds.add(decoding.readVarUint(trackDecoder))
              decoding.readVarUint(trackDecoder) // clock
              decoding.readVarString(trackDecoder) // state JSON
            }
          } catch (e) { /* tracking is best-effort */ }
          break
        }
      }
    } catch (err) {
      console.error('Error handling message:', err)
    }
  })

  ws.on('close', () => {
    // Remove awareness states for this connection's clients
    if (controlledIds.size > 0) {
      removeAwarenessStates(room.awareness, Array.from(controlledIds), null)
    }

    room.conns.delete(ws)

    // Clean up empty rooms
    if (room.conns.size === 0) {
      room.awareness.destroy()
      room.doc.destroy()
      rooms.delete(roomName)
    }
  })
})

console.log(`Yjs WebSocket server running on ws://localhost:${port}`)
