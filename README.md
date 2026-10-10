# Tesseract OCR microservice
This small Docker container allows to run Tesseract OCR on PDF documents via HTTP POST.
To do so, a PDF document is submitted as "userfile" form data.
The container returns the PDF with an invisible text layer added. By default the pages are re-rendered as JPEG at 300 dpi first (a lossless 300 dpi colour scan page is about 4 MB, as JPEG a few hundred KB); with `OCR_RENDER_DPI=0` the original page images are kept untouched. Black-and-white (1-bit) pages are always kept as they are.

OCR is done by [ocrmypdf](https://ocrmypdf.readthedocs.io/) with tesseract. Pages that already contain text are passed through unchanged.

## Example
```
docker build -t ocr .
docker run -p8080:80 ocr
curl -F "userfile=@/tmp/test.pdf" -H "Expect:" -o output.pdf localhost:8080/ocr.php
```

## Options

| Where | Name | Meaning |
|---|---|---|
| form field | `lang` | tesseract language(s) for this request, e.g. `deu` or `deu+eng` (default: `OCR_LANGUAGE`) |
| environment | `OCR_LANGUAGE` | default language (default: `deu`) |
| environment | `OCR_JOBS` | parallel tesseract jobs per document (default: 2) |
| environment | `OCR_RENDER_DPI` | pages are re-rendered as JPEG at this dpi before OCR so the result stays small; never more than the page's own resolution; `0` keeps the original images untouched (default: 300) |
| environment | `OCR_JPEG_QUALITY` | JPEG quality for the re-render (default: 85) |

Only languages whose `tesseract-ocr-<lang>` package is installed in the image work; the image ships `deu` and `eng`. Add packages in the Dockerfile for more.

## Responses

| Status | Meaning |
|---|---|
| 200 | body is the OCR'd PDF (`Content-Type: application/pdf`) |
| 400 | no `userfile`, not a PDF, or invalid `lang` |
| 405 | not a POST |
| 500 | OCR failed; the body contains the last lines of the ocrmypdf output |

The service never answers 200 without a valid PDF, so callers can rely on the status code.
