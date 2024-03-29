module.exports = {
    testRegex: 'resources/js/tests.*.spec.js$',
    testEnvironment: "jsdom",
    moduleFileExtensions: [
        'js',
        'json',
        'vue',
    ],
    transform: {
        '^.+\\.vue$': 'vue-jest',
        '^.+\\.js$': 'babel-jest',
    },
    moduleNameMapper: {
        '^@/(.*)$': '<rootDir>/resources/$1',
        '^~/(.*)$': '<rootDir>/resources/js/$1',
    },
    transformIgnorePatterns: [
        '/node_modules/'
    ]
};
