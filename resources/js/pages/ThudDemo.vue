<template>
    <div class="game-container">
        <div class="info">
            <h2>{{ currentPlayer }}'s Turn</h2>
        </div>
        <div class="board-container">
        <!-- Column Indicators -->
        <div class="column-indicators">
            <div v-for="col in columns" :key="col" class="column-indicator">{{ col }}</div>
        </div>
        <div class="board">
            <div v-for="(row, rowIndex) in board" :key="rowIndex" class="row">
                <!-- Row Indicator -->
                <div class="row-indicator">{{ rowIndex + 1 }}</div>

                <!-- Cells -->
                <div
                    v-for="(cell, colIndex) in row"
                    :key="colIndex"
                    class="cell"
                    :class="{
              'selected': isSelected(rowIndex, colIndex),
              'inactive': !cell.active,
              'white': (rowIndex + colIndex) % 2 === 0,
              'gray': (rowIndex + colIndex) % 2 !== 0
            }"
                    @dragstart="dragStart($event, rowIndex, colIndex)"
                    @dragover.prevent
                    @drop="drop($event, rowIndex, colIndex)"
                    draggable="true"
                >
                    <img v-if="cell.piece" :src="getPieceImage(cell.piece)" :alt="cell.piece.type" />
                </div>
            </div>
        </div>
        </div>

        <div class="log-panel">
            <h2>Game Log</h2>
            <div v-for="log in logs" :key="log" class="log-entry">{{ log }}</div>
        </div>
    </div>
</template>

<script>
export default {
    data() {
        return {
            columns: 'A B C D E F G H I J K L M N O'.split(' '),
            logs: [], // Store game logs
            board: [
                // Define a 9x9 grid representing the Thud board
                // (Some cells will be set as invalid to simulate an octagonal board)
                // Initial positions with dwarfs around the edge and trolls near the center
                [
                    { piece: { type: 'disabled' } }, { piece: { type: 'disabled' } }, { piece: { type: 'disabled' } }, { piece: { type: 'disabled' } }, { piece: { type: 'disabled' } }, { piece: { type: 'dwarf' } }, { piece: { type: 'dwarf' } }, { piece: null }, { piece: { type: 'dwarf' } }, { piece: { type: 'dwarf' }  }, { piece: { type: 'disabled' } }, { piece: { type: 'disabled' } }, { piece: { type: 'disabled' } }, { piece: { type: 'disabled' } }, { piece: { type: 'disabled' } }
                ],
                [
                    { piece: { type: 'disabled' } }, { piece: { type: 'disabled' } }, { piece: { type: 'disabled' } }, { piece: { type: 'disabled' } }, { piece: { type: 'dwarf' }  }, { piece: null }, { piece: null }, { piece: null }, { piece: null }, { piece: null }, { piece:  { type: 'dwarf' } }, { piece: { type: 'disabled' } }, { piece: { type: 'disabled' } }, { piece: { type: 'disabled' } }, { piece: { type: 'disabled' } }
                ],
                [
                    { piece: { type: 'disabled' } }, { piece: { type: 'disabled' } }, { piece: { type: 'disabled' } }, { piece: { type: 'dwarf' }  }, { piece: null }, { piece: null }, { piece: null }, { piece: null }, { piece: null }, { piece: null }, { piece: null }, { piece:  { type: 'dwarf' } }, { piece: { type: 'disabled' } }, { piece: { type: 'disabled' } }, { piece: { type: 'disabled' } }
                ],
                [
                    { piece: { type: 'disabled' } }, { piece: { type: 'disabled' } }, { piece: { type: 'dwarf' }  }, { piece: null }, { piece: null}, { piece: null }, { piece: null }, { piece: null }, { piece: null}, { piece: null }, { piece: null }, { piece: null }, { piece: { type: 'dwarf' } }, { piece: { type: 'disabled' } }, { piece: { type: 'disabled' } }
                ],
                [
                    { piece: { type: 'disabled' } }, { piece: { type: 'dwarf' }  }, { piece: null }, { piece: null}, { piece: null }, { piece: null}, { piece: null }, { piece: null }, { piece: null }, { piece: null }, { piece: null}, { piece: null }, { piece: null }, { piece: { type: 'dwarf' } }, { piece: { type: 'disabled' } }
                ],
                [
                    { piece: { type: 'dwarf' } }, { piece: null }, { piece: null }, { piece: null }, { piece: null}, { piece: null }, { piece: null }, { piece: null }, { piece: null }, { piece: null }, { piece: null }, { piece: null }, { piece: null }, { piece: null }, { piece: { type: 'dwarf' } }
                ],
                [
                    { piece: { type: 'dwarf' } }, { piece: null }, { piece: null }, { piece: null }, { piece: null }, { piece: null }, { piece: { type: 'troll' } }, { piece: { type: 'troll' } }, { piece: { type: 'troll' } }, { piece: null }, { piece: null }, { piece: null }, { piece: null }, { piece: null }, { piece: { type: 'dwarf' } }
                ],
                [
                    { piece: null }, { piece: null }, { piece: null }, { piece: null }, { piece: null }, { piece: null }, { piece: { type: 'troll' } }, { piece: { type: 'thudstone' } }, { piece: { type: 'troll' } }, { piece: null }, { piece: null }, { piece: null }, { piece: null }, { piece: null }, { piece: null }
                ],
                [
                    { piece: { type: 'dwarf' } }, { piece: null }, { piece:null }, { piece:null }, { piece:null }, { piece:null }, { piece:{ type: 'troll' } }, { piece:{ type: 'troll' } }, { piece:{ type: 'troll' } }, { piece: null }, { piece: null }, { piece: null }, { piece: null }, { piece: null }, { piece: { type: 'dwarf' } }
                ],
                [
                    { piece: { type: 'dwarf' } }, { piece: null }, { piece:null }, { piece:null }, { piece:null }, { piece:null }, { piece:null }, { piece:null }, { piece:null }, { piece: null }, { piece: null }, { piece: null }, { piece: null }, { piece: null }, { piece: { type: 'dwarf' } }
                ],
                [
                    { piece: { type: 'disabled' } }, { piece: { type: 'dwarf' }  }, { piece: null }, { piece: null}, { piece: null }, { piece: null}, { piece: null }, { piece: null }, { piece: null }, { piece: null }, { piece: null}, { piece: null }, { piece: null }, { piece: { type: 'dwarf' } }, { piece: { type: 'disabled' } }
                ],
                [
                    { piece: { type: 'disabled' } }, { piece: { type: 'disabled' } }, { piece: { type: 'dwarf' }  }, { piece: null }, { piece: null}, { piece: null }, { piece: null }, { piece: null }, { piece: null}, { piece: null }, { piece: null }, { piece: null }, { piece: { type: 'dwarf' } }, { piece: { type: 'disabled' } }, { piece: { type: 'disabled' } }
                ],
                [
                    { piece: { type: 'disabled' } }, { piece: { type: 'disabled' } }, { piece: { type: 'disabled' } }, { piece: { type: 'dwarf' }  }, { piece: null }, { piece: null }, { piece: null }, { piece: null }, { piece: null }, { piece: null }, { piece: null }, { piece:  { type: 'dwarf' } }, { piece: { type: 'disabled' } }, { piece: { type: 'disabled' } }, { piece: { type: 'disabled' } }
                ],
                [
                    { piece: { type: 'disabled' } }, { piece: { type: 'disabled' } }, { piece: { type: 'disabled' } }, { piece: { type: 'disabled' } }, { piece: { type: 'dwarf' }  }, { piece: null }, { piece: null }, { piece: null }, { piece: null }, { piece: null }, { piece:  { type: 'dwarf' } }, { piece: { type: 'disabled' } }, { piece: { type: 'disabled' } }, { piece: { type: 'disabled' } }, { piece: { type: 'disabled' } }
                ],
                [
                    { piece: { type: 'disabled' } }, { piece: { type: 'disabled' } }, { piece: { type: 'disabled' } }, { piece: { type: 'disabled' } }, { piece: { type: 'disabled' } }, { piece: { type: 'dwarf' } }, { piece: { type: 'dwarf' } }, { piece: null }, { piece: { type: 'dwarf' } }, { piece: { type: 'dwarf' }  }, { piece: { type: 'disabled' } }, { piece: { type: 'disabled' } }, { piece: { type: 'disabled' } }, { piece: { type: 'disabled' } }, { piece: { type: 'disabled' } }
                ]
            ],
            selectedCell: null, // Track the currently selected cell
            currentPlayer: 'Dwarf', // Track whose turn it is
            draggingPiece: null, // Track the piece being dragged
        };
    },
    methods: {
        logEvent(message) {
            // Add event to the log
            this.logs.unshift(message);
        },
        dragStart(event, rowIndex, colIndex) {
            const cell = this.board[rowIndex][colIndex];
            if (cell.piece && this.isCurrentPlayerPiece(cell.piece)) {
                this.draggingPiece = { piece: cell.piece, from: { rowIndex, colIndex } };
            } else {
                event.preventDefault();
            }
        },
        drop(event, rowIndex, colIndex) {
            if (this.draggingPiece) {
                const from = this.draggingPiece.from;
                if (this.isValidMove(from.rowIndex, from.colIndex, rowIndex, colIndex)) {
                    this.board[rowIndex][colIndex].piece = this.draggingPiece.piece;
                    this.board[from.rowIndex][from.colIndex].piece = null;
                    this.handleCapture(rowIndex, colIndex);

                    // Log move
                    this.logEvent(
                        `${this.draggingPiece.piece.type.toUpperCase()} moved from ${this.columns[from.colIndex]}${from.rowIndex + 1} to ${this.columns[colIndex]}${rowIndex + 1}`
                    );


                    this.draggingPiece = null;
                    this.togglePlayer();
                }  else {
                    this.logEvent(`Invalid move from ${this.columns[from.colIndex]}${from.rowIndex + 1} to ${this.columns[colIndex]}${rowIndex + 1}`);
                }
            }
        },
        isValidMove(fromRow, fromCol, toRow, toCol) {
           // this.logEvent(`Invalid move from ${}`);
            const piece = this.board[fromRow][fromCol].piece;
            if (!piece || this.board[toRow][toCol].piece) return false;

            const rowDiff = Math.abs(toRow - fromRow);
            const colDiff = Math.abs(toCol - fromCol);

            if (piece.type === 'dwarf') {
                // Dwarfs can move in straight lines or diagonally
                return rowDiff === colDiff || rowDiff === 0 || colDiff === 0;
            } else if (piece.type === 'troll') {
                // Trolls can move based on the number of supporting trolls behind them
                const direction = { row: Math.sign(toRow - fromRow), col: Math.sign(toCol - fromCol) };
                const supportingTrolls = this.countSupportingPieces(fromRow, fromCol, direction, 'troll');
                const maxDistance = supportingTrolls + 1;

                return (rowDiff === colDiff || rowDiff === 0 || colDiff === 0) && Math.max(rowDiff, colDiff) <= maxDistance;
            }

            return false;
        },
        handleCapture(rowIndex, colIndex) {
            const piece = this.board[rowIndex][colIndex].piece;
            if (piece.type === 'troll') {
                // Troll captures dwarfs
                this.captureDwarfs(rowIndex, colIndex);
            } else if (piece.type === 'dwarf') {
                // Dwarfs capture trolls
                this.captureTrolls(rowIndex, colIndex);
            }
        },

        captureDwarfs(rowIndex, colIndex) {
            // Troll captures dwarfs in adjacent positions
            const directions = [
                { r: -1, c: 0 }, { r: 1, c: 0 }, { r: 0, c: -1 }, { r: 0, c: 1 },
                { r: -1, c: -1 }, { r: -1, c: 1 }, { r: 1, c: -1 }, { r: 1, c: 1 }
            ];

            directions.forEach(({ r, c }) => {
                const newRow = rowIndex + r;
                const newCol = colIndex + c;
                if (this.isOnBoard(newRow, newCol)) {
                    const target = this.board[newRow][newCol];
                    if (target.piece && target.piece.type === 'dwarf') {
                        this.board[newRow][newCol].piece = null;
                        this.logEvent(`TROLL at ${this.columns[colIndex]}${rowIndex + 1} captured DWARF at ${this.columns[newCol]}${newRow + 1}`);
                    }
                }
            });
        },

        captureTrolls(rowIndex, colIndex) {
            const piece = this.board[rowIndex][colIndex].piece;
            const directions = [
                { r: -1, c: 0 }, { r: 1, c: 0 }, { r: 0, c: -1 }, { r: 0, c: 1 },
                { r: -1, c: -1 }, { r: -1, c: 1 }, { r: 1, c: -1 }, { r: 1, c: 1 }
            ];

            directions.forEach(({ r, c }) => {
                let newRow = rowIndex + r;
                let newCol = colIndex + c;

                if (this.isOnBoard(newRow, newCol)) {
                    const target = this.board[newRow][newCol];
                    if (target.piece && target.piece.type === 'troll') {
                        // Check if there are enough supporting dwarfs behind the dwarf that made the move
                        const supportingDwarfs = this.countSupportingPieces(rowIndex, colIndex, { r: -r, c: -c }, 'dwarf');
                        const distance = Math.max(Math.abs(newRow - rowIndex), Math.abs(newCol - colIndex));

                        if (supportingDwarfs >= distance) {
                            this.board[newRow][newCol].piece = null;
                            this.logEvent(`DWARF at ${this.columns[colIndex]}${rowIndex + 1} captured TROLL at ${this.columns[newCol]}${newRow + 1}`);
                        }
                    }
                }
            });
        },

        countSupportingPieces(row, col, direction, type) {
            let count = 0;
            while (true) {
                row -= direction.row;
                col -= direction.col;
                if (!this.isOnBoard(row, col)) break;

                const piece = this.board[row][col].piece;
                if (!piece || piece.type !== type) break;

                count++;
            }
            this.logEvent('Supportes' + count)
            return count;
        },

        isOnBoard(row, col) {
            return row >= 0 && row < this.board.length && col >= 0 && col < this.board[0].length;
        },
        isSelected(rowIndex, colIndex) {
            return (
                this.draggingPiece &&
                this.draggingPiece.from.rowIndex === rowIndex &&
                this.draggingPiece.from.colIndex === colIndex
            );
        },
        getPieceImage(piece) {
            if (piece.type === 'dwarf') {
                return 'https://via.placeholder.com/50x50?text=D'; // Replace with actual dwarf image URL
            } else if (piece.type === 'troll') {
                return 'https://via.placeholder.com/50x50?text=T'; // Replace with actual troll image URL
            } else if (piece.type === 'thudstone') {
                return 'https://via.placeholder.com/50x50?text=S'; // Replace with actual thudstone image URL
            }else if (piece.type === 'disabled') {
                return 'https://via.placeholder.com/50x50?text=X'; // Replace with actual thudstone image URL
            }
            return '';
        },
        isCurrentPlayerPiece(piece) {
            return (
                (this.currentPlayer === 'Dwarf' && piece.type === 'dwarf') ||
                (this.currentPlayer === 'Troll' && piece.type === 'troll')
            );
        },
        togglePlayer() {
            this.currentPlayer = this.currentPlayer === 'Dwarf' ? 'Troll' : 'Dwarf';
        },
    },
};
</script>

<style scoped>
.game-container {
    display: flex;
    flex-direction: row;
    justify-content: space-around;
    text-align: center;
}

.board-container {
    display: flex;
    flex-direction: column;
    align-items: center;
}

.column-indicators {
    display: grid;
    grid-template-columns: repeat(15, 50px);
    margin-bottom: 5px;
}

.column-indicator {
    width: 50px;
    height: 20px;
}

.board {
    display: grid;
    grid-template-rows: repeat(15, 50px);
    grid-gap: 2px;
}

.row {
    display: grid;
    grid-template-columns: 20px repeat(15, 50px);
}

.row-indicator {
    width: 20px;
    height: 50px;
    line-height: 50px;
}

.cell {
    width: 50px;
    height: 50px;
    border: 1px solid #ccc;
    display: flex;
    justify-content: center;
    align-items: center;
    cursor: pointer;
}

.cell.white {
    background-color: #fff;
}

.cell.gray {
    background-color: #ccc;
}

.cell.inactive {
    background-color: #888;
    cursor: default;
}

.cell.selected {
    background-color: #cfc;
}

.log-panel {
    width: 300px;
    height: 600px;
    overflow-y: auto;
    border: 1px solid #ccc;
    padding: 10px;
    background-color: #f9f9f9;
}

.log-entry {
    margin-bottom: 5px;
    font-size: 14px;
}
</style>
