/**
 * What a line of chat looks like as a comic panel.
 *
 * Comic Chat read the text itself: emoticons, punctuation and shouting
 * decided the face a character pulled and the shape of its balloon. The
 * member can always override this by picking an emotion themselves.
 */

const EMOTICONS = [
    { emotion: 'happy', patterns: [':)', ':-)', ':d', ':-d', '=)', '(:', '^^', '🙂', '😀', '😄', 'haha', 'hehe', 'lol'] },
    { emotion: 'sad', patterns: [':(', ':-(', ":'(", '=(', '😢', '😭', 'sorry'] },
    { emotion: 'angry', patterns: ['>:(', '>:-(', '😠', '😡', 'grr'] },
    { emotion: 'surprised', patterns: [':o', ':-o', '😮', '😲', 'wow', 'whoa'] },
    { emotion: 'confused', patterns: ['🤔', 'hmm', 'huh'] },
    { emotion: 'excited', patterns: [':d', '😃', '🎉', 'yay', 'woo'] },
];

const EMOTIONS = ['normal', 'happy', 'sad', 'angry', 'surprised', 'confused', 'excited'];
const BUBBLES = ['speech', 'thought', 'shout', 'whisper', 'action'];
const GESTURES = ['wave', 'laugh', 'think', 'shout', 'whisper'];

const stripTags = (text) => String(text || '').replace(/<[^>]*>/g, ' ');

/**
 * Whether the line is shouted: written in capitals, or ending in !!
 */
export function isShouting(text) {
    const clean = stripTags(text).trim();
    if (!clean) return false;

    if (/!{2,}$/.test(clean)) return true;

    const letters = clean.replace(/[^a-zA-ZäöüÄÖÜß]/g, '');
    return letters.length >= 4 && letters === letters.toUpperCase();
}

/**
 * The face for a line of chat: an emoticon wins, then punctuation.
 */
export function inferEmotion(text) {
    const clean = stripTags(text).toLowerCase();

    if (!clean.trim()) return 'normal';

    for (const { emotion, patterns } of EMOTICONS) {
        if (patterns.some(pattern => clean.includes(pattern))) {
            return emotion;
        }
    }

    if (isShouting(text)) return 'angry';
    if (clean.includes('?')) return 'confused';
    if (clean.includes('!')) return 'excited';

    return 'normal';
}

/**
 * The balloon a line is drawn in.
 */
export function inferBubble(text, { type = 'message' } = {}) {
    const clean = stripTags(text).trim();

    if (type === 'action') return 'action';
    if (/^\(.*\)$/.test(clean)) return 'thought';
    if (isShouting(text)) return 'shout';
    if (/^<.*>$/.test(clean) || clean.startsWith('psst')) return 'whisper';

    return 'speech';
}

/**
 * How a message should be drawn: what the member chose, and where they
 * chose nothing, what the text itself suggests.
 */
export function expressionOf(message = {}) {
    // Anything the message carries has to be one of the known values — the
    // names end up as css classes and as the parts a character is drawn from
    const chosenEmotion = EMOTIONS.includes(message.emotion) && message.emotion !== 'normal' ? message.emotion : null;
    const chosenBubble = BUBBLES.includes(message.bubble_type) && message.bubble_type !== 'speech' ? message.bubble_type : null;
    const gesture = GESTURES.includes(message.gesture) ? message.gesture : null;

    const gestureBubble = { think: 'thought', shout: 'shout', whisper: 'whisper' }[gesture];

    return {
        emotion: chosenEmotion || inferEmotion(message.message),
        bubble: chosenBubble || gestureBubble || inferBubble(message.message, { type: message.type }),
        gesture,
    };
}
