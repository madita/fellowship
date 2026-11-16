import { useI18n } from 'vue-i18n';

export function useTicketHelpers() {
    const { t } = useI18n();

    const statusOptions = [
        { value: 'open', color: 'info' },
        { value: 'in_progress', color: 'warning' },
        { value: 'pending', color: 'orange' },
        { value: 'resolved', color: 'success' },
        { value: 'closed', color: 'grey' },
    ];

    const priorityOptions = [
        { value: 'low', color: 'grey', icon: 'mdi-arrow-down' },
        { value: 'normal', color: 'info', icon: 'mdi-minus' },
        { value: 'high', color: 'warning', icon: 'mdi-arrow-up' },
        { value: 'urgent', color: 'error', icon: 'mdi-alert' },
    ];

    const getStatusColor = (status) => {
        return statusOptions.find(s => s.value === status)?.color || 'grey';
    };

    const getStatusLabel = (status) => {
        return t(`tickets.status.${status}`);
    };

    const getPriorityColor = (priority) => {
        return priorityOptions.find(p => p.value === priority)?.color || 'grey';
    };

    const getPriorityIcon = (priority) => {
        return priorityOptions.find(p => p.value === priority)?.icon || 'mdi-minus';
    };

    const getPriorityLabel = (priority) => {
        return t(`tickets.priority.${priority}`);
    };

    const statusFilterOptions = (includeAll = true) => {
        const options = statusOptions.map(s => ({
            value: s.value,
            label: getStatusLabel(s.value),
            color: s.color,
        }));
        if (includeAll) {
            options.unshift({ value: null, label: t('tickets.filters.allStatuses') });
        }
        return options;
    };

    const priorityFilterOptions = (includeAll = true) => {
        const options = priorityOptions.map(p => ({
            value: p.value,
            label: getPriorityLabel(p.value),
            color: p.color,
            icon: p.icon,
        }));
        if (includeAll) {
            options.unshift({ value: null, label: t('tickets.filters.allPriorities') });
        }
        return options;
    };

    return {
        statusOptions,
        priorityOptions,
        getStatusColor,
        getStatusLabel,
        getPriorityColor,
        getPriorityIcon,
        getPriorityLabel,
        statusFilterOptions,
        priorityFilterOptions,
    };
}
