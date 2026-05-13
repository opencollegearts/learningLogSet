/**
 * Scroll-triggered fade-in for learning log post images and content.
 * Elements with .learninglog-fade-in get .learninglog-visible when they enter the viewport.
 */
define('mod_learninglog/scrollfade', [], function() {
    return {
        init: function() {
            // Grid tiles already use .learninglog-fade-in.
            // Post detail pages may not: ensure all post images get the class.
            var postImages = document.querySelectorAll('.learninglog-post-detail img');
            postImages.forEach(function(el) {
                if (!el.classList.contains('learninglog-fade-in')) {
                    el.classList.add('learninglog-fade-in');
                }
            });

            var elements = document.querySelectorAll('.learninglog-fade-in');
            if (!elements.length) {
                return;
            }
            var observer = new IntersectionObserver(function(entries) {
                entries.forEach(function(entry) {
                    if (entry.isIntersecting) {
                        entry.target.classList.add('learninglog-visible');
                    }
                });
            }, {
                rootMargin: '0px 0px -40px 0px',
                threshold: 0.05
            });
            elements.forEach(function(el) {
                observer.observe(el);
            });
        }
    };
});
