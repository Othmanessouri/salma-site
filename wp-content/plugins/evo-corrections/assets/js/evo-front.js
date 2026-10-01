/* EVO Corrections — comportements ajoutés au site. */
(function () {
	'use strict';

	var root = document.documentElement;
	var body = document.body;
	var header = document.querySelector('.ekit-template-content-header');

	/* G4 · G19 : hauteur du header pour le décalage des ancres + réduction au défilement. */
	function setHeaderHeight() {
		if (header) {
			root.style.setProperty('--evo-header-h', header.offsetHeight + 'px');
		}
	}
	var ticking = false;
	function onScroll() {
		if (ticking) {
			return;
		}
		ticking = true;
		window.requestAnimationFrame(function () {
			body.classList.toggle('evo-scrolled', window.scrollY > 40);
			ticking = false;
		});
	}
	setHeaderHeight();
	onScroll();
	window.addEventListener('scroll', onScroll, { passive: true });
	window.addEventListener('resize', setHeaderHeight);
	if (header && 'ResizeObserver' in window) {
		new ResizeObserver(setHeaderHeight).observe(header);
	}

	/* Arrivée sur une ancre depuis une autre page : recalage sous le header collant. */
	if (window.location.hash && window.location.hash.length > 1) {
		window.addEventListener('load', function () {
			var target = null;
			try {
				target = document.querySelector(decodeURIComponent(window.location.hash));
			} catch (e) {}
			if (target) {
				setTimeout(function () {
					target.scrollIntoView({ block: 'start' });
				}, 60);
			}
		});
	}

	/* ACC9 : un seul carrousel horizontal des 7 marques sur mobile. */
	var main = document.querySelector('.evo-brands-main');
	var extra = document.querySelector('.evo-brands-extra');
	if (main && extra && window.matchMedia) {
		var mq = window.matchMedia('(max-width: 767px)');
		var moved = [];
		var containerOf = function (el) {
			return el.querySelector(':scope > .e-con-inner') || el;
		};
		var apply = function () {
			var from = containerOf(extra);
			var to = containerOf(main);
			if (mq.matches && !moved.length) {
				Array.prototype.slice.call(from.children).forEach(function (card) {
					if (card.classList.contains('e-con') && card.children.length) {
						moved.push(card);
						to.appendChild(card);
					}
				});
				main.classList.add('evo-carousel');
				extra.classList.add('evo-emptied');
			} else if (!mq.matches && moved.length) {
				moved.forEach(function (card) {
					from.appendChild(card);
				});
				moved = [];
				main.classList.remove('evo-carousel');
				extra.classList.remove('evo-emptied');
			}
		};
		apply();
		if (mq.addEventListener) {
			mq.addEventListener('change', apply);
		}
	}

	/* SUI1 : conversions GA4 (si un ID est renseigné). */
	var cfg = window.EVO_CORR || {};
	if (cfg.ga4) {
		var send = function (name, params) {
			if (typeof window.gtag === 'function') {
				window.gtag('event', name, params || {});
			}
		};
		if (cfg.merci) {
			send('generate_lead', { form_page: document.referrer || '' });
		}
		document.addEventListener('click', function (e) {
			var a = e.target.closest ? e.target.closest('a[href]') : null;
			if (!a) {
				return;
			}
			var href = a.getAttribute('href') || '';
			if (/wa\.me|api\.whatsapp\.com/.test(href)) {
				send('contact_whatsapp', { link_url: href });
			} else if (/^tel:/.test(href)) {
				send('contact_phone', { link_url: href });
			} else if (/\.pdf($|\?)|\/telechargement\//.test(href)) {
				send('file_download', { link_url: href, file_name: href.split('/').filter(Boolean).pop() });
			}
		});
	}
})();
