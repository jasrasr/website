const receipt = document.querySelector('[data-receipt]');
if (receipt) {
    const lines = receipt.querySelector('[data-receipt-lines]');
    const addLine = receipt.querySelector('[data-add-receipt-line]');
    const template = lines.firstElementChild.cloneNode(true);
    addLine.hidden = false;
    addLine.addEventListener('click', () => {
        const index = lines.children.length;
        const line = template.cloneNode(true);
        for (const field of line.querySelectorAll('[name]')) {
            field.name = field.name.replace(/^lines\[0\]/, `lines[${index}]`);
        }
        lines.append(line);
        line.querySelector('select').focus();
        addLine.disabled = lines.children.length >= 50;
    });
}
