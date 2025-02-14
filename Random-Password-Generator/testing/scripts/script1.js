document.addEventListener('DOMContentLoaded', () => {
    const inputs = document.querySelectorAll('#lengthSlider, #includeUppercase, #includeLowercase, #includeNumbers, #includeSymbols, #excludeAmbiguous, #excludeExtraSpecial');
    inputs.forEach(input => input.addEventListener('input', generatePassword));
    document.getElementById('generateButton').addEventListener('click', generatePassword);
    generatePassword(); // Generate a password on initial load
});

function updateLengthValue(value) {
    document.getElementById('lengthValue').textContent = value;
}

function generatePassword() {
    const length = document.getElementById('lengthSlider').value;
    const includeUppercase = document.getElementById('includeUppercase').checked;
    const includeLowercase = document.getElementById('includeLowercase').checked;
    const includeNumbers = document.getElementById('includeNumbers').checked;
    const includeSymbols = document.getElementById('includeSymbols').checked;
    const excludeAmbiguous = document.getElementById('excludeAmbiguous').checked;
    const excludeExtraSpecial = document.getElementById('excludeExtraSpecial').checked;

    let uppercaseChars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ';
    let lowercaseChars = 'abcdefghijklmnopqrstuvwxyz';
    let numberChars = '0123456789';
    let symbolChars = '!@#$%^&*+~?><-=';

    const ambiguousChars = '/\\|IiLl1oO0';
    const extraSpecialChars = '()_`|}{[]:;,./';

    if (excludeAmbiguous) {
        uppercaseChars = removeCharacters(uppercaseChars, ambiguousChars);
        lowercaseChars = removeCharacters(lowercaseChars, ambiguousChars);
        numberChars = removeCharacters(numberChars, ambiguousChars);
    }

    if (excludeExtraSpecial) {
        symbolChars = removeCharacters(symbolChars, extraSpecialChars);
    }

    let charSet = '';
    if (includeUppercase) charSet += uppercaseChars;
    if (includeLowercase) charSet += lowercaseChars;
    if (includeNumbers) charSet += numberChars;
    if (includeSymbols) charSet += symbolChars;

    if (charSet === '') {
        alert('Please select at least one character type.');
        return;
    }

    let password = '';
    const cryptoObj = window.crypto || window.msCrypto;
    const randomValues = new Uint32Array(length);
    cryptoObj.getRandomValues(randomValues);

    for (let i = 0; i < length; i++) {
        const char = charSet.charAt(randomValues[i] % charSet.length);
        password += wrapCharacterWithSpan(char);
    }

    document
::contentReference[oaicite:0]{index=0}
 
