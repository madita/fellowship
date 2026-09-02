<template>
    <div class="game-demo">
        <div class="room-description">
            <h2>{{ currentRoom.name }}</h2>
            <p>{{ currentRoom.description }}</p>
        </div>
        <div class="room-details">
            <h3>Items in this room:</h3>
            <ul>
                <li v-for="item in currentRoom.items" :key="item.id">{{ item.name }}: {{ item.description }}</li>
            </ul>
            <h3>NPCs in this room:</h3>
            <ul>
                <li v-for="npc in currentRoom.npcs" :key="npc.id">
                    {{ npc.name }}: {{ npc.health > 0 ? npc.description : 'Dead' }}
                </li>
            </ul>
        </div>
        <div class="inventory">
            <h3>Your Inventory:</h3>
            <ul>
                <li v-for="item in inventory" :key="item.id">{{ item.name }}</li>
            </ul>
        </div>
        <div class="character-stats">
            <h3>Your Stats:</h3>
            <p>Health: {{ character.health }}</p>
            <p>Strength: {{ character.strength }}</p>
            <p>Intelligence: {{ character.intelligence }}</p>
        </div>
        <div class="game-log">
            <div v-for="log in gameLog" :key="log.id" class="log-entry">{{ log.text }}</div>
        </div>
        <div class="command-input">
            <input v-model="command" @keydown.enter="processCommand" placeholder="Enter command" />
        </div>
        <div class="room-navigation">
            <button @click="move('norden')" :disabled="!currentRoom.north_room_id">Norden</button>
            <button @click="move('sueden')" :disabled="!currentRoom.south_room_id">Süden</button>
            <button @click="move('osten')" :disabled="!currentRoom.east_room_id">Osten</button>
            <button @click="move('westen')" :disabled="!currentRoom.west_room_id">Westen</button>
        </div>
    </div>
</template>

<script>
export default {
    data() {
        return {
            rooms: [
                {
                    id: 1,
                    name: "Dungeon",
                    description: "A dark and gloomy dungeon.",
                    items: [
                        { id: 1, name: "Schwert", description: "Ein scharfes Schwert.", attack: 10, defense: 2 },
                        { id: 2, name: "Schild", description: "Ein stabiler Schild.", attack: 2, defense: 10 }
                    ],
                    npcs: [
                        { id: 1, name: "Goblin", description: "Ein fieser Goblin.", health: 30, strength: 5, intelligence: 3, loot: [{ id: 4, name: "Goblin's Dolch", description: "Ein grober Dolch.", attack: 5, defense: 1 }] }
                    ],
                    north_room_id: 2,
                    south_room_id: null,
                    east_room_id: null,
                    west_room_id: null
                },
                {
                    id: 2,
                    name: "Schlosshof",
                    description: "Ein weitläufiger Hof mit einem Brunnen in der Mitte.",
                    items: [
                        { id: 3, name: "Münze", description: "Eine glänzende Goldmünze.", attack: 0, defense: 0 }
                    ],
                    npcs: [
                        { id: 2, name: "Wächter", description: "Ein wachsamer Schlosswächter.", health: 50, strength: 10, intelligence: 5, loot: [] }
                    ],
                    north_room_id: null,
                    south_room_id: 1,
                    east_room_id: null,
                    west_room_id: null
                }
            ],
            currentRoom: {},
            inventory: [],
            character: {
                health: 100,
                strength: 10,
                intelligence: 10,
                currentAttack: null,
            },
            gameLog: [],
            command: '',
            isAttacking: false,
            currentNpc: null,
            attackInterval: null
        };
    },
    created() {
        this.currentRoom = this.rooms[0];
    },
    methods: {
        move(direction) {
            if (this.isAttacking) {
                this.addToLog("Du kannst dich nicht bewegen, während du kämpfst!");
                return;
            }
            const nextRoomId = this.currentRoom[`${direction}_room_id`];
            if (nextRoomId) {
                this.currentRoom = this.rooms.find(room => room.id === nextRoomId);
                this.addToLog(`Du gehst nach ${direction} in den ${this.currentRoom.name}.`);
                this.look();
            } else {
                this.addToLog(`Du kannst nicht in diese Richtung gehen.`);
            }
        },
        processCommand() {
            const input = this.command.trim().toLowerCase();
            const [action, ...args] = input.split(' ');

            if (action === 'betrachte') {
                if (args.length > 0) {
                    this.lookAt(args.join(' '));
                } else {
                    this.look();
                }
            } else if (action === 'nimm') {
                if (args.length > 0) {
                    this.takeItem(args.join(' '));
                } else {
                    this.addToLog(`Du musst sagen, was du nehmen willst.`);
                }
            } else if (action === 'toete') {
                const npcName = args.slice(0, args.indexOf('mit')).join(' ');
                const itemName = args.slice(args.indexOf('mit') + 1).join(' ');
                this.attackNpc(npcName, itemName);
            } else if (action === 'benutze') {
                if (args.length === 1) {
                    this.useItem(args[0]);
                } else if (args.includes('mit')) {
                    const item1 = args.slice(0, args.indexOf('mit')).join(' ');
                    const item2 = args.slice(args.indexOf('mit') + 1).join(' ');
                    this.useItemWith(item1, item2);
                } else {
                    this.addToLog(`Ungültige Benutzung des Befehls.`);
                }
            } else if (action === 'stop') {
                this.stopAttack();
            } else if (action === 'plündere') {
                if (args.length > 0) {
                    this.lootNpc(args.join(' '));
                } else {
                    this.addToLog(`Du musst sagen, wen du plündern willst.`);
                }
            } else if (action === 'sage') {
                if (args.length > 0) {
                    this.saySomething(args.join(' '));
                } else {
                    this.addToLog(`Du musst etwas sagen.`);
                }
            } else if (action === 'rede') {
                if (args.length > 0) {
                    this.redeWith(args[0], args.slice(1).join(' '));
                } else {
                    this.addToLog(`Du musst angeben, mit wem du reden möchtest und was du sagen willst.`);
                }
            } else {
                this.addToLog(`Unbekannter Befehl: ${input}`);
            }

            this.command = '';
        },
        look() {
            this.addToLog(`Du bist im ${this.currentRoom.name}. ${this.currentRoom.description}`);
        },
        lookAt(target) {
            const npc = this.currentRoom.npcs.find(n => n.name.toLowerCase() === target);
            if (npc) {
                if (npc.health > 0) {
                    this.addToLog(`Du betrachtest ${npc.name}. Es scheint ein ${npc.description} zu sein. Gesundheit: ${npc.health}, Stärke: ${npc.strength}, Intelligenz: ${npc.intelligence}.`);
                } else {
                    this.addToLog(`Der ${npc.name} ist tot.`);
                }
            } else {
                this.addToLog(`Es gibt hier keinen ${target}.`);
            }
        },
        takeItem(target) {
            const itemIndex = this.currentRoom.items.findIndex(i => i.name.toLowerCase() === target);
            if (itemIndex !== -1) {
                const item = this.currentRoom.items.splice(itemIndex, 1)[0];
                this.inventory.push(item);
                this.addToLog(`Du nimmst den ${item.name}.`);
            } else {
                this.addToLog(`Es gibt hier keinen ${target}.`);
            }
        },
        attackNpc(npcName, itemName) {
            if (this.isAttacking) {
                this.addToLog("Du bist bereits im Kampf!");
                return;
            }
            const npc = this.currentRoom.npcs.find(n => n.name.toLowerCase() === npcName);
            const item = this.inventory.find(i => i.name.toLowerCase() === itemName);
            if (npc && item) {
                this.addToLog(`Du greifst den ${npc.name} mit dem ${item.name} an.`);
                this.isAttacking = true;
                this.currentNpc = npc;
                this.character.currentAttack = item;
                this.startCombat(npc, item);
            } else if (!npc) {
                this.addToLog(`Es gibt hier keinen ${npcName} zum Angreifen.`);
            } else if (!item) {
                this.addToLog(`Du hast keinen ${itemName} zum Angreifen.`);
            }
        },
        startCombat(npc, item) {
            this.attackInterval = setInterval(() => {
                const playerDamage = this.character.strength + item.attack;
                npc.health -= playerDamage;
                this.addToLog(`Du triffst den ${npc.name} und verursachst ${playerDamage} Schaden. Er hat ${npc.health > 0 ? npc.health : 'keine'} Gesundheit mehr.`);

                if (npc.health <= 0) {
                    this.addToLog(`Du hast den ${npc.name} besiegt!`);
                    this.stopAttack(true);
                    return;
                }

                const npcDamage = npc.strength - item.defense;
                this.character.health -= npcDamage;
                this.addToLog(`Der ${npc.name} trifft dich und verursacht ${npcDamage} Schaden. Du hast noch ${this.character.health} Gesundheit.`);

                if (this.character.health <= 0) {
                    this.addToLog("Du wurdest besiegt!");
                    this.stopAttack(true);
                }
            }, 1000);
        },
        stopAttack(forceStop = false) {
            if (this.isAttacking) {
                clearInterval(this.attackInterval);
                this.isAttacking = false;
                if (!forceStop) {
                    this.addToLog("Du hast den Angriff gestoppt.");
                    if (Math.random() > 0.5) {
                        this.addToLog(`Der ${this.currentNpc.name} läuft weg.`);
                        this.currentRoom.npcs = this.currentRoom.npcs.filter(n => n.id !== this.currentNpc.id);
                    } else {
                        this.addToLog(`Der ${this.currentNpc.name} greift weiter an! Du versuchst zu fliehen.`);
                        this.moveToRandomRoom();
                    }
                }
            }
        },
        moveToRandomRoom() {
            const possibleExits = ['north_room_id', 'south_room_id', 'east_room_id', 'west_room_id'].filter(exit => this.currentRoom[exit]);
            const randomExit = possibleExits[Math.floor(Math.random() * possibleExits.length)];
            if (randomExit) {
                const nextRoomId = this.currentRoom[randomExit];
                if (nextRoomId) {
                    this.currentRoom = this.rooms.find(room => room.id === nextRoomId);
                    this.addToLog(`Du fliehst in den ${this.currentRoom.name}.`);
                    this.look();
                }
            }
        },
        lootNpc(target) {
            const npc = this.currentRoom.npcs.find(n => n.name.toLowerCase() === target && n.health <= 0);
            if (npc) {
                if (npc.loot && npc.loot.length > 0) {
                    npc.loot.forEach(item => {
                        this.inventory.push(item);
                        this.addToLog(`Du plünderst den ${item.name} vom ${npc.name}.`);
                    });
                    npc.loot = [];
                } else {
                    this.addToLog(`Der ${npc.name} hat nichts zu plündern.`);
                }
            } else {
                this.addToLog(`Es gibt keinen toten ${target} hier zum Plündern.`);
            }
        },
        useItem(itemName) {
            const item = this.inventory.find(i => i.name.toLowerCase() === itemName);
            if (item) {
                this.addToLog(`Du benutzt den ${item.name}.`);
            } else {
                this.addToLog(`Du hast keinen ${itemName} zum Benutzen.`);
            }
        },
        useItemWith(itemName1, itemName2) {
            const item1 = this.inventory.find(i => i.name.toLowerCase() === itemName1);
            const item2 = this.inventory.find(i => i.name.toLowerCase() === itemName2);
            if (item1 && item2) {
                this.addToLog(`Du benutzt den ${item1.name} mit dem ${item2.name}.`);
            } else {
                this.addToLog(`Du hast nicht beide Gegenstände zum Benutzen.`);
            }
        },
        addToLog(text) {
            this.gameLog.push({ id: this.gameLog.length + 1, text });
        },
        saySomething(message) {
            this.addToLog(`Du sagst: "${message}"`);
            this.currentRoom.npcs.forEach(npc => {
                this.npcResponse(npc, message);
            });
        },
        redeWith(npcName, message) {
            const npc = this.currentRoom.npcs.find(n => n.name.toLowerCase() === npcName.toLowerCase());
            if (npc) {
                this.addToLog(`Du sprichst zu ${npc.name}: "${message}"`);
                this.npcResponse(npc, message);
            } else {
                this.addToLog(`Es gibt hier keinen ${npcName} zum Ansprechen.`);
            }
        },
        npcResponse(npc, message) {
            // Example NPC response logic based on the message
            if (npc.name.toLowerCase() === "goblin" && message.toLowerCase().includes("hallo")) {
                this.addToLog(`${npc.name} grunzt: "Was willst du, Mensch?"`);
            } else if (npc.name.toLowerCase() === "wächter" && message.toLowerCase().includes("hilfe")) {
                this.addToLog(`${npc.name} sagt: "Ich werde dir helfen, aber nur dieses Mal!"`);
            } else {
                this.addToLog(`${npc.name} reagiert nicht.`);
            }
        }
    }
};
</script>

<style scoped>
.game-demo {
    font-family: Arial, sans-serif;
    max-width: 600px;
    margin: 0 auto;
}
.room-description {
    border-bottom: 1px solid #ccc;
    padding-bottom: 15px;
    margin-bottom: 15px;
}
.room-details h3 {
    margin-bottom: 10px;
}
.room-navigation button {
    margin-right: 10px;
}
.command-input {
    margin-top: 20px;
}
.game-log {
    margin-top: 20px;
    padding: 10px;
    background-color: #f9f9f9;
    border: 1px solid #ddd;
}
.log-entry {
    margin-bottom: 5px;
}
.inventory, .character-stats {
    margin-top: 20px;
}
</style>
s
