for (const time of document.querySelectorAll('time[data-local]')) {
    const date = new Date(time.dateTime);
    time.textContent = time.hasAttribute('data-date-only')
        ? date.toLocaleDateString()
        : date.toLocaleString();
}
