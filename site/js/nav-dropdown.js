/* 窄屏导航：点一下一级项，展开/收起它的二级菜单。
   样式全部在 templatemo_style.css 末尾的 `max-width:700px` 段里，这个脚本
   只负责给父 li 加/去 cnido-nav-open 这个类，不写任何内联样式。

   为什么非要脚本：CSS 那边只有 :hover / :focus-within 两条路可走，而它们在
   手机上都靠不住 ——
     - iOS Safari 点 <a> 不会给它 :focus，:focus-within 永远不成立；
     - 触屏的 :hover 是「黏」的：第一次点确实会展开，但在别处再点一下不会收起，
       更点不掉，用户会以为菜单卡死了。
   加了 cnido-nav-open 之后，展开不再依赖焦点，收起也有了确定的手势
   （点空白处，或再点一次同一个一级项）。

   只在窄屏绑定（matchMedia 700px，和 CSS 的断点同一个值）：宽屏是悬停展开，
   一级项本来就只该走悬停那条路，绑上去会让桌面端多出一套"点一下也展开"的
   行为，破坏"桌面端逐像素不变"这条底线。 */
(function () {
	'use strict';

	var OPEN = 'cnido-nav-open';
	var mq = window.matchMedia ? window.matchMedia('(max-width: 700px)') : null;
	var bound = false;

	/* #templatemo_menu > ul（一级列表）。按标签找而不是 :scope >，
	   免得老浏览器不认 :scope。 */
	function topUl() {
		var menu = document.getElementById('templatemo_menu');
		if (!menu) return null;
		var kids = menu.children;
		for (var i = 0; i < kids.length; i++) {
			if (kids[i].tagName === 'UL') return kids[i];
		}
		return null;
	}

	function subOf(li) {
		var kids = li.children;
		for (var i = 0; i < kids.length; i++) {
			if (kids[i].tagName === 'UL') return kids[i];
		}
		return null;
	}

	function closeAll(except) {
		var ul = topUl();
		if (!ul) return;
		for (var i = 0; i < ul.children.length; i++) {
			var li = ul.children[i];
			if (li === except) continue;
			li.classList.remove(OPEN);
			var sub = subOf(li);
			if (sub) sub.style.maxHeight = '';   /* 收起时清掉内联限高 */
		}
	}

	/* CSS 里给的 max-height:72vh 只是个够不着的兜底。面板是从导航条底边往下
	   铺的，导航条本身在 375px 宽的手机上就占掉 142px，最长的一份二级菜单
	   （Genome，17 项约 800px）会有一截落到视口外。给面板自己加滚动条也救不了
	   —— 手指在面板上滑只会滚面板，滚不动页面，最后两项就永远够不着。
	   所以展开时按"面板顶边到视口底边还剩多少"现算一个限高，让它整份都进屏。 */
	function fit(li) {
		var sub = subOf(li);
		if (!sub) return;
		var top = sub.getBoundingClientRect().top;
		var room = Math.max(160, window.innerHeight - top - 10);
		sub.style.maxHeight = Math.min(window.innerHeight - 16, room) + 'px';
	}

	function openLi(li) {
		li.classList.add(OPEN);
		fit(li);
	}

	function onClick(e) {
		if (e.defaultPrevented || e.button > 1) return;
		if (e.metaKey || e.ctrlKey || e.shiftKey || e.altKey) return;

		var a = e.target;
		while (a && a.tagName !== 'A') a = a.parentNode;
		if (!a || a.tagName !== 'A') return;

		var ul = topUl();
		var li = a.parentNode;
		/* 只管一级项：二级菜单里的链接是正常导航，不能拦。 */
		if (!ul || !li || li.parentNode !== ul) return;
		if (!subOf(li)) return;           /* Home / Download 这类没有子菜单 */

		/* href="#" 会滚回页首，挡掉；菜单该跳转的项都不是 "#"。 */
		e.preventDefault();

		var willOpen = !li.classList.contains(OPEN);
		closeAll(null);                   /* 同时只开一个 */
		if (willOpen) openLi(li);
	}

	function onClickAway(e) {
		var menu = document.getElementById('templatemo_menu');
		if (menu && menu.contains(e.target)) return;
		closeAll(null);
	}

	function onResize() {
		var ul = topUl();
		if (!ul) return;
		for (var i = 0; i < ul.children.length; i++) {
			if (ul.children[i].classList.contains(OPEN)) fit(ul.children[i]);
		}
	}

	function bind(on) {
		if (on === bound) return;
		bound = on;
		var fn = on ? 'addEventListener' : 'removeEventListener';
		document[fn]('click', onClick, false);
		document[fn]('click', onClickAway, false);
		window[fn]('resize', onResize, false);
		window[fn]('orientationchange', onResize, false);
	}

	function sync() {
		var on = mq ? mq.matches : true;
		bind(on);
		if (!on) closeAll(null);
	}

	if (mq) {
		if (mq.addEventListener) mq.addEventListener('change', sync);
		else if (mq.addListener) mq.addListener(sync);
	}
	sync();
})();
