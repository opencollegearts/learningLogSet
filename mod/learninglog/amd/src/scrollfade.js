/**
 * Scroll-triggered fade-in for learning log post images and content.
 * Elements with .learninglog-fade-in get .learninglog-visible when they enter the viewport.
 */
define('mod_learninglog/scrollfade', [], function() {
    return {
        init: function() {
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
