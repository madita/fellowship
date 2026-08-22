<template>
  <g v-html="svgContent"></g>
</template>

<script>
export default {
  name: 'ComicCharacter',
  props: {
    character: { type: String, default: 'cat' },
    emotion: { type: String, default: 'normal' },
    color: { type: Number, default: 200 },
  },
  computed: {
    bodyColor() {
      return `hsl(${this.color}, 55%, 75%)`;
    },
    bodyDark() {
      return `hsl(${this.color}, 55%, 60%)`;
    },
    eyes() {
      const e = this.emotion;
      if (e === 'happy') return { left: '&#x25E1;', right: '&#x25E1;', yOff: 0 };
      if (e === 'sad') return { left: '&#x2565;', right: '&#x2565;', yOff: 2 };
      if (e === 'angry') return { left: '&gt;', right: '&lt;', yOff: 0 };
      if (e === 'surprised') return { left: 'O', right: 'O', yOff: -2 };
      if (e === 'confused') return { left: '?', right: '&#x2022;', yOff: 0 };
      if (e === 'excited') return { left: '&#x2605;', right: '&#x2605;', yOff: -2 };
      return { left: '&#x2022;', right: '&#x2022;', yOff: 0 };
    },
    mouth() {
      const e = this.emotion;
      if (e === 'happy' || e === 'excited') return 'M 38 72 Q 50 82 62 72';
      if (e === 'sad') return 'M 38 76 Q 50 68 62 76';
      if (e === 'angry') return 'M 38 76 L 62 76';
      if (e === 'surprised') return 'M 44 72 Q 50 80 56 72 Q 50 80 44 72';
      if (e === 'confused') return 'M 40 74 Q 48 70 56 76';
      return 'M 42 74 Q 50 78 58 74';
    },
    svgContent() {
      const parts = [];
      const c = this.character;
      const col = this.bodyColor;
      const colD = this.bodyDark;

      // --- Body & Head ---
      if (c === 'cat') {
        parts.push(`<ellipse cx="50" cy="100" rx="28" ry="35" fill="${col}" stroke="#333" stroke-width="2"/>`);
        parts.push(`<circle cx="50" cy="52" r="26" fill="${col}" stroke="#333" stroke-width="2"/>`);
        parts.push(`<polygon points="30,38 24,14 42,32" fill="${col}" stroke="#333" stroke-width="2"/>`);
        parts.push(`<polygon points="70,38 76,14 58,32" fill="${col}" stroke="#333" stroke-width="2"/>`);
        parts.push(`<polygon points="31,36 27,20 40,33" fill="${colD}"/>`);
        parts.push(`<polygon points="69,36 73,20 60,33" fill="${colD}"/>`);
        parts.push(`<line x1="12" y1="60" x2="34" y2="62" stroke="#888" stroke-width="1"/>`);
        parts.push(`<line x1="12" y1="66" x2="34" y2="65" stroke="#888" stroke-width="1"/>`);
        parts.push(`<line x1="88" y1="60" x2="66" y2="62" stroke="#888" stroke-width="1"/>`);
        parts.push(`<line x1="88" y1="66" x2="66" y2="65" stroke="#888" stroke-width="1"/>`);
      } else if (c === 'dog') {
        parts.push(`<ellipse cx="50" cy="100" rx="28" ry="35" fill="${col}" stroke="#333" stroke-width="2"/>`);
        parts.push(`<circle cx="50" cy="52" r="26" fill="${col}" stroke="#333" stroke-width="2"/>`);
        parts.push(`<ellipse cx="26" cy="48" rx="12" ry="18" fill="${colD}" stroke="#333" stroke-width="2" transform="rotate(-10 26 48)"/>`);
        parts.push(`<ellipse cx="74" cy="48" rx="12" ry="18" fill="${colD}" stroke="#333" stroke-width="2" transform="rotate(10 74 48)"/>`);
        parts.push(`<ellipse cx="50" cy="64" rx="12" ry="8" fill="${colD}" stroke="#333" stroke-width="1.5"/>`);
        parts.push(`<ellipse cx="50" cy="60" rx="4" ry="3" fill="#333"/>`);
      } else if (c === 'robot') {
        parts.push(`<rect x="24" y="68" width="52" height="64" rx="4" fill="${col}" stroke="#333" stroke-width="2"/>`);
        parts.push(`<rect x="26" y="42" width="48" height="32" rx="6" fill="${col}" stroke="#333" stroke-width="2"/>`);
        parts.push(`<line x1="50" y1="42" x2="50" y2="28" stroke="#333" stroke-width="2"/>`);
        parts.push(`<circle cx="50" cy="26" r="5" fill="#f44336" stroke="#333" stroke-width="1.5"/>`);
        parts.push(`<rect x="34" y="82" width="32" height="14" rx="2" fill="${colD}" stroke="#333" stroke-width="1"/>`);
        // Bolts
        parts.push(`<circle cx="36" cy="89" r="2" fill="#fdd835"/>`);
        parts.push(`<circle cx="44" cy="89" r="2" fill="#4caf50"/>`);
        parts.push(`<circle cx="52" cy="89" r="2" fill="#f44336"/>`);
      } else if (c === 'alien') {
        parts.push(`<ellipse cx="50" cy="100" rx="24" ry="32" fill="${col}" stroke="#333" stroke-width="2"/>`);
        parts.push(`<ellipse cx="50" cy="50" rx="28" ry="24" fill="${col}" stroke="#333" stroke-width="2"/>`);
        parts.push(`<line x1="38" y1="30" x2="30" y2="12" stroke="#333" stroke-width="2"/>`);
        parts.push(`<circle cx="30" cy="10" r="4" fill="#76ff03" stroke="#333" stroke-width="1"/>`);
        parts.push(`<line x1="62" y1="30" x2="70" y2="12" stroke="#333" stroke-width="2"/>`);
        parts.push(`<circle cx="70" cy="10" r="4" fill="#76ff03" stroke="#333" stroke-width="1"/>`);
      } else if (c === 'wizard') {
        parts.push(`<ellipse cx="50" cy="100" rx="28" ry="35" fill="${col}" stroke="#333" stroke-width="2"/>`);
        parts.push(`<circle cx="50" cy="52" r="26" fill="${col}" stroke="#333" stroke-width="2"/>`);
        parts.push(`<polygon points="50,2 28,42 72,42" fill="#4a148c" stroke="#333" stroke-width="2"/>`);
        parts.push(`<ellipse cx="50" cy="42" rx="26" ry="6" fill="#6a1b9a" stroke="#333" stroke-width="2"/>`);
        parts.push(`<text x="50" y="30" text-anchor="middle" fill="#ffd54f" font-size="14">&#9733;</text>`);
        // Beard
        parts.push(`<path d="M 38 70 Q 42 90 50 92 Q 58 90 62 70" fill="#e0e0e0" stroke="#bbb" stroke-width="1"/>`);
      } else if (c === 'ninja') {
        parts.push(`<ellipse cx="50" cy="100" rx="26" ry="34" fill="#444" stroke="#333" stroke-width="2"/>`);
        parts.push(`<circle cx="50" cy="52" r="26" fill="#444" stroke="#333" stroke-width="2"/>`);
        parts.push(`<rect x="24" y="44" width="52" height="18" rx="8" fill="#222"/>`);
        // Belt
        parts.push(`<rect x="28" y="84" width="44" height="6" rx="2" fill="#8d6e63" stroke="#333" stroke-width="1"/>`);
        parts.push(`<circle cx="50" cy="87" r="4" fill="#fdd835" stroke="#333" stroke-width="1"/>`);
      } else if (c === 'pirate') {
        parts.push(`<ellipse cx="50" cy="100" rx="26" ry="34" fill="${col}" stroke="#333" stroke-width="2"/>`);
        parts.push(`<circle cx="50" cy="52" r="26" fill="${col}" stroke="#333" stroke-width="2"/>`);
        parts.push(`<path d="M 24 44 Q 50 20 76 44 L 76 48 Q 50 40 24 48 Z" fill="#333"/>`);
        parts.push(`<text x="50" y="40" text-anchor="middle" fill="white" font-size="10">&#9760;</text>`);
        parts.push(`<ellipse cx="38" cy="56" rx="8" ry="6" fill="#333"/>`);
        parts.push(`<line x1="38" y1="50" x2="50" y2="36" stroke="#333" stroke-width="1.5"/>`);
      } else if (c === 'knight') {
        parts.push(`<ellipse cx="50" cy="100" rx="28" ry="35" fill="#bdbdbd" stroke="#333" stroke-width="2"/>`);
        parts.push(`<circle cx="50" cy="52" r="26" fill="#bdbdbd" stroke="#333" stroke-width="2"/>`);
        parts.push(`<rect x="30" y="48" width="40" height="14" rx="4" fill="#9e9e9e" stroke="#333" stroke-width="1.5"/>`);
        // Visor slits
        parts.push(`<line x1="36" y1="54" x2="46" y2="54" stroke="#555" stroke-width="2"/>`);
        parts.push(`<line x1="54" y1="54" x2="64" y2="54" stroke="#555" stroke-width="2"/>`);
        // Plume
        parts.push(`<path d="M 50 26 Q 60 8 72 26 Q 62 20 50 26" fill="#f44336" stroke="#c62828" stroke-width="1"/>`);
        // Shield emblem on chest
        parts.push(`<path d="M 42 88 L 50 82 L 58 88 L 58 100 Q 50 106 42 100 Z" fill="#1565c0" stroke="#333" stroke-width="1"/>`);
        parts.push(`<text x="50" y="98" text-anchor="middle" fill="#ffd54f" font-size="8">&#9733;</text>`);
      }

      // --- Eyes ---
      if (c !== 'knight') {
        const eyeY = 54 + this.eyes.yOff;
        const eyeColor = c === 'ninja' ? 'white' : '#333';
        parts.push(`<text x="40" y="${eyeY}" text-anchor="middle" fill="${eyeColor}" font-size="12" font-weight="bold">${this.eyes.left}</text>`);
        if (c !== 'pirate') {
          parts.push(`<text x="60" y="${eyeY}" text-anchor="middle" fill="${eyeColor}" font-size="12" font-weight="bold">${this.eyes.right}</text>`);
        }
      }

      // --- Mouth ---
      if (c !== 'ninja' && c !== 'knight') {
        parts.push(`<path d="${this.mouth}" fill="none" stroke="#333" stroke-width="2" stroke-linecap="round"/>`);
      }

      // --- Arms ---
      parts.push(`<line x1="24" y1="88" x2="8" y2="102" stroke="#333" stroke-width="2.5" stroke-linecap="round"/>`);
      parts.push(`<line x1="76" y1="88" x2="92" y2="102" stroke="#333" stroke-width="2.5" stroke-linecap="round"/>`);

      // --- Feet ---
      parts.push(`<ellipse cx="38" cy="134" rx="10" ry="5" fill="${c === 'knight' ? '#757575' : colD}" stroke="#333" stroke-width="1.5"/>`);
      parts.push(`<ellipse cx="62" cy="134" rx="10" ry="5" fill="${c === 'knight' ? '#757575' : colD}" stroke="#333" stroke-width="1.5"/>`);

      return parts.join('');
    },
  },
};
</script>
