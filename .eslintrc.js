const defaultEslintrc = require('10up-toolkit/config/.eslintrc');

module.exports = {
	...defaultEslintrc,
	globals: {
		jQuery: true,
	},
	settings: {
		...defaultEslintrc.settings,
		'import/resolver': {
			typescript: {},
			node: {
				extensions: ['.js', '.jsx', '.ts', '.tsx'],
			},
		},
	},
};
