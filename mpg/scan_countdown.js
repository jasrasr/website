(() => {
    const scanBtn = document.getElementById('scanBtn');
    const loading = document.getElementById('loading');
    if (!scanBtn || !loading) return;

    let timer = null;

    function stopCountdown() {
        if (timer !== null) {
            clearInterval(timer);
            timer = null;
        }
    }

    function startCountdown() {
        stopCountdown();
        let remaining = 10;
        loading.textContent = `Reading photos with AI… ${remaining}s`;

        timer = setInterval(() => {
            // scan_photos.php hides this as soon as the AI request completes.
            // Stop immediately so the countdown never delays parsed results.
            if (loading.style.display !== 'block') {
                stopCountdown();
                return;
            }

            remaining--;
            if (remaining > 0) {
                loading.textContent = `Reading photos with AI… ${remaining}s`;
            } else {
                loading.textContent = 'Reading photos with AI… still working';
                stopCountdown();
            }
        }, 1000);
    }

    scanBtn.addEventListener('click', startCountdown);
})();
