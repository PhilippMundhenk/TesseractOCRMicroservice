# Tesseract OCR microservice
This small Docker container allows to run Tesseract OCR on PDF documents via HTTP POST.
To do so, a PDF document is submitted as "userfile" form data.
The container returns a PDF document with 300 DPI resolution and all recognized text included.

## Example
```
curl -F "userfile=@/home/mundheph/private/repositories/OCR/test.pdf" -H "Expect:" -o output.pdf localhost:8080/index.php
```

## Limitations
Currently, Tesseract settings like language, etc. are hard-coded. These should be made configurable via the REST interface.
