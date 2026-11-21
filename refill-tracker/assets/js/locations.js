for (const input of document.querySelectorAll('input[list="storage-locations"], input[list="purchase-locations"]')) {
    const options = input.list.options;
    if (!options.length) {
        continue;
    }

    const picker = document.createElement('select');
    const prompt = input.list.id === 'storage-locations' ? 'Choose a saved storage location' : 'Choose a saved seller';
    picker.setAttribute('aria-label', prompt);
    picker.dataset.locationPicker = '';
    picker.add(new Option(prompt, ''));
    for (const option of options) {
        picker.add(new Option(option.value, option.value));
    }
    input.closest('label').after(picker);
}

// Receipt rows clone the picker along with the text input.
document.addEventListener('change', (event) => {
    const picker = event.target;
    if (picker.matches('select[data-location-picker]') && picker.value !== '') {
        picker.previousElementSibling.querySelector('input').value = picker.value;
        picker.selectedIndex = 0;
    }
});
