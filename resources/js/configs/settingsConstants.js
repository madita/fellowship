export const languages = [
    { code: 'en', name: 'English' },
    { code: 'de', name: 'Deutsch (German)' },
    { code: 'es', name: 'Español (Spanish)' },
    { code: 'fr', name: 'Français (French)' },
    { code: 'it', name: 'Italiano (Italian)' },
    { code: 'pt', name: 'Português (Portuguese)' },
    { code: 'ja', name: '日本語 (Japanese)' },
    { code: 'zh', name: '中文 (Chinese)' },
];

export const timezones = [
    'UTC',
    'America/New_York',
    'America/Chicago',
    'America/Denver',
    'America/Los_Angeles',
    'Europe/London',
    'Europe/Paris',
    'Europe/Berlin',
    'Europe/Rome',
    'Asia/Tokyo',
    'Asia/Shanghai',
    'Asia/Singapore',
    'Australia/Sydney',
    'Pacific/Auckland',
];

export const dateFormats = [
    { label: 'YYYY-MM-DD (2025-12-11)', value: 'Y-m-d' },
    { label: 'DD/MM/YYYY (11/12/2025)', value: 'd/m/Y' },
    { label: 'MM/DD/YYYY (12/11/2025)', value: 'm/d/Y' },
    { label: 'DD.MM.YYYY (11.12.2025)', value: 'd.m.Y' },
];

export const timeFormats = [
    { label: '24-hour (14:30:00)', value: 'H:i:s' },
    { label: '12-hour (02:30:00 PM)', value: 'h:i:s A' },
    { label: '24-hour no seconds (14:30)', value: 'H:i' },
    { label: '12-hour no seconds (02:30 PM)', value: 'h:i A' },
];

export const currencies = [
    { label: 'US Dollar ($)', value: 'USD' },
    { label: 'Euro (€)', value: 'EUR' },
    { label: 'British Pound (£)', value: 'GBP' },
    { label: 'Japanese Yen (¥)', value: 'JPY' },
    { label: 'Canadian Dollar (C$)', value: 'CAD' },
    { label: 'Australian Dollar (A$)', value: 'AUD' },
    { label: 'Swiss Franc (Fr)', value: 'CHF' },
    { label: 'Chinese Yuan (¥)', value: 'CNY' },
    { label: 'Indian Rupee (₹)', value: 'INR' },
    { label: 'Brazilian Real (R$)', value: 'BRL' },
];

export const currencyPositions = [
    { label: 'Before ($100)', value: 'before' },
    { label: 'After (100$)', value: 'after' },
];

export const numberLocales = [
    { label: 'English - US (1,234.56)', value: 'en_US' },
    { label: 'English - UK (1,234.56)', value: 'en_GB' },
    { label: 'German (1.234,56)', value: 'de_DE' },
    { label: 'French (1 234,56)', value: 'fr_FR' },
    { label: 'Spanish (1.234,56)', value: 'es_ES' },
    { label: 'Italian (1.234,56)', value: 'it_IT' },
    { label: 'Dutch (1.234,56)', value: 'nl_NL' },
    { label: 'Portuguese - Brazil (1.234,56)', value: 'pt_BR' },
    { label: 'Japanese (1,234.56)', value: 'ja_JP' },
    { label: 'Chinese (1,234.56)', value: 'zh_CN' },
];

export const themeModes = [
    { label: 'Light Mode', value: 'light' },
    { label: 'Dark Mode', value: 'dark' },
    { label: 'System (Auto)', value: 'system' },
];

export const fontFamilies = [
    { label: 'Roboto (Default)', value: 'Roboto, sans-serif' },
    { label: 'Open Sans', value: '"Open Sans", sans-serif' },
    { label: 'Lato', value: 'Lato, sans-serif' },
    { label: 'Montserrat', value: 'Montserrat, sans-serif' },
    { label: 'Raleway', value: 'Raleway, sans-serif' },
    { label: 'Poppins', value: 'Poppins, sans-serif' },
    { label: 'Inter', value: 'Inter, sans-serif' },
    { label: 'Source Sans Pro', value: '"Source Sans Pro", sans-serif' },
    { label: 'Arial', value: 'Arial, sans-serif' },
    { label: 'Helvetica', value: 'Helvetica, sans-serif' },
];

export const twitterCardTypes = [
    { label: 'Summary', value: 'summary' },
    { label: 'Summary with Large Image', value: 'summary_large_image' },
    { label: 'App', value: 'app' },
    { label: 'Player', value: 'player' },
];

export const newsletterProviders = [
    { label: 'None', value: 'none' },
    { label: 'Mailchimp', value: 'mailchimp' },
    { label: 'SendGrid', value: 'sendgrid' },
    { label: 'ConvertKit', value: 'convertkit' },
    { label: 'MailerLite', value: 'mailerlite' },
];
