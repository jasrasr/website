document.addEventListener('DOMContentLoaded', () => {
    const inputs = document.querySelectorAll('#lengthSlider, #includeUppercase, #minUppercase, #includeLowercase, #minLowercase, #includeNumbers, #minNumbers, #includeSymbols, #minSymbols');
    inputs.forEach(input => input.addEventListener('input', generatePassword));
    document.getElementById('generateButton').addEventListener('click', generatePassword);
    generatePassword();
});

function updateLengthValue(value) {
    document.getElementById('lengthValue').textContent = value;
}

function generatePassword() {
    const lengthSlider = document.getElementById('lengthSlider');
    const length = parseInt(lengthSlider.value);
    updateLengthValue(length);

    const includeUppercase = document.getElementById('includeUppercase').checked;
    const includeLowercase = document.getElementById('includeLowercase').checked;
    const includeNumbers = document.getElementById('includeNumbers').checked;
    const includeSymbols = document.getElementById('includeSymbols').checked;

    const minUppercase = parseInt(document.getElementById('minUppercase').value);
    const minLowercase = parseInt(document.getElementById('minLowercase').value);
    const minNumbers = parseInt(document.getElementById('minNumbers').value);
    const minSymbols = parseInt(document.getElementById('minSymbols').value);

    const uppercaseChars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    const lowercaseChars = 'abcdefghijklmnopqrstuvwxyz';
    const numberChars = '0123456789';
    const symbolChars = '!@#$%^&*()_+~`|}{[]:;?><,./-=';

    let charSet = '';
    let password = '';

    if (includeUppercase) charSet += uppercaseChars;
    if (includeLowercase) charSet += lowercaseChars;
    if (includeNumbers) charSet += numberChars;
    if (includeSymbols) charSet += symbolChars;

    if (charSet === '') {
        alert('Please select at least one character type.');
        return;
    }

    const totalMinChars = minUppercase + minLowercase + minNumbers + minSymbols;
    if (totalMinChars > length) {
        alert('Total
::contentReference[oaicite:0]{index=0}
 
