'use strict';

const EventEmitter = require('events');
const exec = require('child_process').exec;

class Gimle extends EventEmitter {
	constructor (base) {
		super();

		this._config = {};

		this.runScript(base + 'module/gimle/cli/getconfig.php', (r) => {
			try {
				this._config = JSON.parse(r);
			}
			catch (e) {
				console.log('Config', r);
				process.exit();
			}
			if ((this._config.subsite !== undefined) && (this._config.subsite.of !== undefined)) {
				this._subconfig = {};
				let siteids = Object.keys(this._config.subsite.of);
				for (let siteid of siteids) {
					this.runScript(base + 'module/gimle/cli/getconfig.php ' + siteid, (r) => {
						this._subconfig[siteid] = JSON.parse(r);
					});
				}
			}
			this.emit('ready');
		});

		this.ENV_WEB = 1;
		this.ENV_CLI = 2;
		this.ENV_LOCAL = 4;
		this.ENV_DEV = 8;
		this.ENV_INTEGRATION = 16;
		this.ENV_TEST = 32;
		this.ENV_QA = 64;
		this.ENV_UAT = 128;
		this.ENV_STAGE = 256;
		this.ENV_DEMO = 512;
		this.ENV_PREPROD = 1024;
		this.ENV_LIVE = 2048;
		this.SITE_DIR = base;
		this.SITE_ID = this.SITE_DIR.slice(this.SITE_DIR.slice(0, -1).lastIndexOf('/') + 1, -1);
		this.MODULE_GIMLE = __dirname.substring(__dirname.lastIndexOf('/') + 1);
	}

	log (message) {
		console.log(`[${new Date().toISOString().replace(/T/, ' ').replace(/\..+/, '')}]:`, message);
	};

	config (key) {
		return this._config[key];
	}

	isInt (value) {
		return (!isNaN(value) && (function (x) {
			return ((x | 0) === x);
		})(parseFloat(value)));
	}

	stringToNestedArray (key, value, separator) {
		separator = separator || '.';
		if (!key.includes(separator)) {
			let tmp = {};
			tmp[key] = value;
			return tmp;
		}
		key = key.split(separator);
		let pre = key.shift();
		let tmp = {};
		tmp[pre] = this.stringToNestedArray(key.join(separator), value, separator);
		return tmp;
	}

	arrayMergeDistinct () {
		let arrays = arguments;
		let array = arrays[0];
		if (arrays.length > 1) {
			for (var first in arrays) {
				break;
			}
			delete arrays[first];
			for (let array2 of arrays) {
				if (array2 !== undefined) {
					for (let key in array2) {
						if (typeof array2[key] === 'object') {
							array[key] = ((array[key] !== undefined) && (typeof array[key] === 'object') ? this.arrayMergeDistinct(array[key], array2[key]) : array2[key]);
						}
						else {
							array[key] = array2[key];
						}
					}
				}
			};
		}
		return array;
	}

	phpGetReturn (filename, callback) {
		let cmd = `php -r "echo json_encode(include '${filename}');"`;
		exec(cmd, (error, stdout, stderr) => {
			callback(JSON.parse(stdout));
		});
	}

	runScript (cmd, callback) {
		exec(cmd, (error, stdout, stderr) => {
			callback(stdout);
		});
	}

	parseConfigFile (filename, callback) {
		let that = this;
		require('fs').readFile(filename, 'utf-8', (error, contents) => {
			let result = {};

			let lastkey = undefined;
			contents.split('\n').forEach(function (linestr, linenum) {
				if (linestr.substr(0, 1) === ';') {
					return;
				}
				if (linestr === '') {
					return;
				}
				let line = linestr.split(' = ');
				let key = line[0];
				if ((line[1] !== undefined) && (line[0].substr(0, 1) !== '[')) {
					let value = undefined;

					if ((line[1].substr(0, 1) === '"') && (line[1].substr(-1) === '"')) {
						value = line[1].slice(1, -1).replace('\\"', '"').replace('\\\\', '\\');
					}
					else if (that.isInt(line[1])) {
						value = parseInt(line[1]);
					}
					else if (line[1] === 'true') {
						value = true;
					}
					else if (line[1] === 'false') {
						value = false;
					}
					else if (line[1] === 'null') {
						value = null;
					}
					else if (that[line[1]] !== undefined) {
						value = that[line[1]];
					}
					else {
						console.log('Unknown value in ini file on line ' + (linenum + 1) + ': ' + linestr);
						process.exit();
					}
					if (value !== undefined) {
						if (lastkey === undefined) {
							result[key] = value;
						}
						else {
							let tmp = {};
							tmp[key] = value;
							result = that.arrayMergeDistinct(result, that.stringToNestedArray(lastkey, tmp));
						}
					}
				}
				else {
					lastkey = key.slice(1, -1);
				}
			});
			callback(result);
		});
	}
}

module.exports = (base) => {
	return new Gimle(base);
}
