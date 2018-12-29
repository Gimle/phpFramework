'use strict';

window.gimle = (() => {
	let gimle = (selector, context) => {
		return new gimle.fn.init(selector, context);
	};

	gimle.fn = gimle.prototype = {
		init: function (selector, context) {
			if (!selector) {
				return this;
			}

			if (typeof selector === 'string') {
				this.selector = document.querySelectorAll(selector);
			}
			else {
				this.selector = [];
				this.selector.push(selector);
			}

			for (let selector of this.selector) {
				if (selector.gimle === undefined) {
					selector.gimle = {};
				}
			}
			this.context = context;
		}
	};

	gimle.fn.init.prototype = gimle.fn;

	gimle.BASE_PATH = '';
	for (let index in document.location.href) {
		if (document.location.href[index] !== document.currentScript.src[index]) {
			break;
		}
		gimle.BASE_PATH += document.location.href[index];
	}

	gimle.const = function () {
		let result = {};
		for (let key of Object.keys(this)) {
			if (/[A-Z_]+/.test(key)) {
				result[key] = this[key];
			}
		}
		return result;
	};

	gimle.selfOrParentMatch = function (element, selector) {
		if ((typeof selector === 'string') || (selector instanceof String))  {
			if (element.matches(selector)) {
				return element;
			}
		}
		else {
			if (element.isSameNode(selector)) {
				return element;
			}
		}
		return ((element.parentElement) && (gimle.selfOrParentMatch(element.parentElement, selector))) || null;
	}

	gimle.parentMatch = function (element, selectorString) {
		element = element.parentElement;
		if (!element) {
			return null;
		}
		if (element.matches(selectorString)) {
			return element;
		}
		return gimle.selfOrParentMatch(element, selectorString);
	}

	gimle.fn.each = function (listen, callback) {
		if (typeof listen === 'function') {
			callback = listen;
			listen = undefined;
		}

		for (let selector of this.selector) {
			if (listen !== undefined) {
				let objects = selector.querySelectorAll(listen);
				for (let object of objects) {
					callback.call(object);
				}
			}
			else {
				callback.call(selector);
			}
		}
	}

	gimle.fn.on = function (type, listen, callback, options, useCapture) {
		if (typeof listen === 'function') {
			useCapture = options;
			options = callback;
			callback = listen;
			listen = undefined;
		}
		if (options === undefined) {
			options = false;
		}
		if (useCapture === undefined) {
			useCapture = false;
		}

		if (options.hash !== undefined) {
			delete options.hash;
		}

		for (let selector of this.selector) {
			let thisEvent = {
				type: type.split('.')[0],
				namespacedType: type,
				selector: selector,
				callback: callback,
				options: options,
				useCapture: useCapture
			};
			if (listen !== undefined) {
				thisEvent.callback = function (e) {
					if (gimle.selfOrParentMatch(e.target, listen) !== null) {
						callback.call(e.target, e);
					}
				};
			}
			selector.addEventListener(thisEvent.type, thisEvent.callback, thisEvent.options, thisEvent.useCapture);
			if (selector.gimle.eventStore === undefined) {
				selector.gimle.eventStore = [];
			}
			selector.gimle.eventStore.push(thisEvent);
		}
	};

	gimle.fn.off = function (type) {
		for (let selector of this.selector) {
			for (let index in selector.gimle.eventStore) {
				let event = selector.gimle.eventStore[index];
				if ((event.type === type) || (event.namespacedType === type)) {
					selector.removeEventListener(event.type, event.callback, event.options, event.useCapture);
					delete selector.gimle.eventStore[index];
				}
				else if ((type.startsWith('.')) && (event.namespacedType.indexOf('.') !== '-1')) {
					if (event.type + type === event.namespacedType) {
						selector.removeEventListener(event.type, event.callback, event.options, event.useCapture);
						delete selector.gimle.eventStore[index];
					}
				}
			}
		}
	};

	return gimle;
})();
