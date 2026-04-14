<template>
    <v-menu v-if="variant === 'menu'" location="bottom end">
        <template #activator="{ props }">
            <v-btn
                v-bind="props"
                :variant="buttonVariant"
                :size="size"
                :icon="iconOnly"
            >
                <v-icon v-if="iconOnly" icon="mdi-translate" />
                <template v-else>
                    <v-icon icon="mdi-translate" start />
                    {{ currentLocaleOption.label }}
                    <v-icon icon="mdi-chevron-down" end />
                </template>
            </v-btn>
        </template>

        <v-list density="compact" nav>
            <v-list-item
                v-for="option in localeOptions"
                :key="option.code"
                :active="option.code === locale"
                @click="setLocale(option.code)"
            >
                <template #prepend>
                    <span class="text-caption font-weight-bold mr-2">
                        {{ option.code.toUpperCase() }}
                    </span>
                </template>
                <v-list-item-title>{{ option.nativeLabel }}</v-list-item-title>
            </v-list-item>
        </v-list>
    </v-menu>

    <v-btn-toggle
        v-else-if="variant === 'toggle'"
        :model-value="locale"
        mandatory
        density="compact"
        color="primary"
    >
        <v-btn
            v-for="option in localeOptions"
            :key="option.code"
            :value="option.code"
            :size="size"
            @click="setLocale(option.code)"
        >
            {{ option.code.toUpperCase() }}
        </v-btn>
    </v-btn-toggle>

    <v-select
        v-else-if="variant === 'select'"
        :model-value="locale"
        :items="localeOptions"
        item-value="code"
        item-title="nativeLabel"
        variant="outlined"
        density="compact"
        hide-details
        :style="{ maxWidth: selectWidth }"
        @update:model-value="setLocale"
    >
        <template #prepend-inner>
            <v-icon icon="mdi-translate" size="small" />
        </template>
    </v-select>
</template>

<script setup>
import { useLocale } from '@/composables/useLocale.js';

const props = defineProps({
    variant: {
        type: String,
        default: 'menu', // 'menu', 'toggle', 'select'
        validator: (v) => ['menu', 'toggle', 'select'].includes(v),
    },
    buttonVariant: {
        type: String,
        default: 'text',
    },
    size: {
        type: String,
        default: 'default',
    },
    iconOnly: {
        type: Boolean,
        default: false,
    },
    selectWidth: {
        type: String,
        default: '140px',
    },
});

const { locale, localeOptions, currentLocaleOption, setLocale } = useLocale();
</script>
