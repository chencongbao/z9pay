(function () {
    var isMobile = window.matchMedia('(max-width: 768px)').matches;
    if (!isMobile) return;

    // 侧边栏：插入遮罩 + 切换按钮
    var mask = document.createElement('div');
    mask.className = 'sidebar-mask';
    document.body.appendChild(mask);

    // 如果顶栏没有汉堡按钮，插入一个
    var navbar = document.querySelector('.navbar');
    if (navbar && !navbar.querySelector('.mobile-toggle')) {
        var btn = document.createElement('a');
        btn.href = 'javascript:void(0)';
        btn.className = 'mobile-toggle';
        btn.innerHTML = '≡';
        var brand = navbar.querySelector('.navbar-custom-menu') || navbar.firstElementChild;
        navbar.insertBefore(btn, brand);
        btn.addEventListener('click', function () {
            document.body.classList.toggle('sidebar-open');
        });
    }

    // 点击遮罩关闭侧边栏
    mask.addEventListener('click', function () {
        document.body.classList.remove('sidebar-open');
    });

    // ===== 筛选器抽屉：为默认“筛选”按钮绑定
    var filterBtn = document.querySelector('.grid-filter .btn, .filter-btn');
    if (filterBtn) {
        var fMask = document.createElement('div');
        fMask.className = 'filter-mask';
        document.body.appendChild(fMask);

        filterBtn.addEventListener('click', function (e) {
            e.preventDefault();
            document.body.classList.add('filter-open');
        });
        fMask.addEventListener('click', function () {
            document.body.classList.remove('filter-open');
        });
    }

    // 表格横向滚动时，避免 iOS 回弹卡顿
    var scrollers = document.querySelectorAll('.table-responsive, .grid-table-wrapper');
    scrollers.forEach(function(el){ el.style.webkitOverflowScrolling = 'touch'; });

})();
