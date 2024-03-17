import { format, parseISO, formatDistance, formatDistanceToNow  } from 'date-fns';
import { enGB, de } from 'date-fns/locale';
import { useSettingsStore } from '../store/settingStore.js'; // Adjust the path

export default {
    install(app) {
        // Get settings store
        const settings = useSettingsStore();

        app.config.globalProperties.$formatDate = function(dateString, formatString = "yyyy-MM-dd") {
            // const date = parseISO(dateString);
            const locale = getLocale(settings.locale);
            return format(new Date(dateString), formatString, { locale });
        };

        app.config.globalProperties.$formatDistance = function(date1, date2 = new Date()) {
            const locale = getLocale(settings.locale);
            return formatDistance(date1, date2, { addSuffix: true, locale });
        };

        app.config.globalProperties.$formatDistanceToNow = function(dateString) {
            const locale = getLocale(settings.locale);
            return formatDistanceToNow(new Date(dateString), { addSuffix: true, locale });
        };

        function getLocale(localeString) {
            switch (localeString) {
                case 'enGB':
                    return enGB;
                case 'de':
                    return de;
                default:
                    return de;
            }
        }
    }
};
