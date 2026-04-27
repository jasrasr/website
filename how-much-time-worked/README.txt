Timeclock Photo Logger - Revision 1.0.0

Upload this folder to your PHP host, for example:
/timeclock/

Required writable folders:
/data/
/uploads/

Default mode is manual OCR review. This is intentional because most shared hosts do not include Tesseract.

To enable local Tesseract OCR:
1. Install tesseract on the server.
2. Edit config.php.
3. Change OCR_MODE from manual to tesseract.

To enable OCR.Space:
1. Get an OCR.Space API key.
2. Edit config.php.
3. Set OCR_MODE to ocrspace.
4. Set OCRSPACE_API_KEY.

Revision 1.0.0 features:
- Upload clock-out slip photo
- Save uploaded images
- Review/correct parsed fields before saving
- Manual entry fallback
- JSON log storage
- Duplicate shift protection
- Recalculate shift minutes from time in/out
- Compare printed shift hours against calculated shift hours
- Stats by employee, ISO week, and month
