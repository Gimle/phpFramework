"use strict";

gimle.hash = {};
gimle.lastHash = [];

gimle.locationHashChanged = function (e) {
	let data = {first: undefined, hashes: [], deep: [{page: [], get: {}, address: '', qs: ''}]};

	let fullHash = document.location.hash.substring(1);
	let hashes = fullHash.match(/([^\\#]|\\\#?)+/g);
	if (hashes !== null) {
		data.hashes = hashes;
		for (let i in data.hashes) {
			i = parseInt(i);
			data.hashes[i] = data.hashes[i].replace('\\#', '#');
			if ((gimle.lastHash.indexOf(i) > -1) || (gimle.lastHash[i] !== data.hashes[i])) {
				if (data.first === undefined) {
					data.first = i;
				}
			}
			let tmp = data.hashes[i].split('?');
			data.deep[i] = {page: [], get: {}, address: tmp[0], qs: tmp[1]};
			let page = tmp[0].split('/');
			if ((page[0] !== '') || (page.length > 1)) {
				data.deep[i].page = page;
			}
			if (tmp[1] !== undefined) {
				let tmp2 = tmp[1].split('&');
				for (let j in tmp2) {
					let tmp3 = tmp2[j].split(/=(.+)?/);
					data.deep[i].get[tmp3[0]] = tmp3[1];
				}
			}
		}
	}
	if (data.first === undefined) {
		data.first = 0;
		if ((hashes !== null) && (gimle.lastHash.length > hashes.length)) {
			data.first = hashes.length;
		}
	}
	gimle.lastHash = data.hashes;

	gimle.hash = data;
	document.dispatchEvent(new CustomEvent('locationHashChanged', {detail: data}));
};

window.onhashchange = gimle.locationHashChanged;
