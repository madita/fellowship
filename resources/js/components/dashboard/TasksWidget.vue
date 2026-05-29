<template>
    <div>
        <div class="mb-3">
            <v-progress-linear
                :model-value="widgetData.total ? (widgetData.completed / widgetData.total) * 100 : 0"
                color="success"
                height="8"
                rounded
            ></v-progress-linear>
            <div class="text-caption mt-1 text-center">
                {{ widgetData.completed || 0 }}/{{ widgetData.total || 0 }} tasks completed
            </div>
        </div>
        <v-list density="compact" class="pa-0">
            <v-list-item
                v-for="task in widgetData.recent?.slice(0, 3)"
                :key="task.id"
                class="px-0 mb-1"
            >
                <template v-slot:prepend>
                    <v-checkbox
                        :model-value="task.completed"
                        color="success"
                        density="compact"
                        hide-details
                    ></v-checkbox>
                </template>
                <v-list-item-title class="text-body-2" :class="{ 'text-decoration-line-through': task.completed }">
                    {{ task.title }}
                </v-list-item-title>
                <v-list-item-subtitle class="text-caption">{{ task.dueDate }}</v-list-item-subtitle>
            </v-list-item>
        </v-list>
    </div>
</template>

<script>
export default {
    name: 'TasksWidget',
    props: {
        widgetData: { type: Object, default: () => ({}) },
        widgetConfig: { type: Object, default: () => ({}) },
    },
};
</script>
