
import { inject } from 'vue';

export default function moment() {
    const $moment = inject('$moment');
    return { $moment };
}
