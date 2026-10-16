# HuffZip — Huffman Coding File Compressor (NLDS Project)

A college-project demo that shows Huffman Coding step by step:
1. Character frequency table
2. Each pair of minimum-frequency nodes combined into N1, N2, ...
3. Final readable Huffman tree with 0/1 branch labels
4. Huffman codes
5. Compress and decompress a file using the C program

## Technology
- C: frequency counting, Huffman tree construction, code generation, binary encoding/decoding
- PHP: server-side bridge to the C executable
- HTML/CSS/JavaScript: interface and interactive step-by-step visualization

## Requirements
- PHP 8+ with `exec()` enabled
- GCC or another C compiler
- For local Windows development, XAMPP plus GCC (e.g. MinGW-w64) available in PATH
- For Linux hosting, PHP and GCC installed

## Run locally
1. Extract this ZIP into your web server's document root, e.g. `C:\xampp\htdocs\HuffZip_NLDS`.
2. Make sure `gcc --version` works in the terminal used by the web server.
3. Open `http://localhost/HuffZip_NLDS/` in your browser.
4. Upload a file and press **Analyze & Build Tree**.
5. Use **Previous Step** and **Next Step** to walk through the combinations.
6. Use **Compress & Download** to create a `.huff` file. Upload a `.huff` file and press **Decompress & Download** to restore it.

## Notes
- This project is intended for a local classroom demo. Do not expose it publicly without adding authentication, rate limits, stronger upload isolation, and server configuration hardening.
- PHP must be allowed to run `exec()` and the web server account needs permission to write to `uploads/` and `outputs/`.
- The C program stores a compact header containing the original byte frequencies before the encoded bitstream. The compressed file format is this project's own `.huff` format.
- Huffman Coding is lossless, but files do not always get smaller because the frequency header adds overhead.
- For equal frequencies, a consistent deterministic tie-breaking rule is used. Other correct tie-breaking rules may produce different, equally valid codes.


## Vercel deployment note

This repository includes a Vercel PHP-runtime layout (`api/index.php`, `api/download.php`, and `vercel.json`) so requests are routed to PHP rather than downloading the root `index.php` as a static file.

Important limitation: the compression backend in `huffman.c` is launched through `exec()` and compiled with `gcc`. Vercel's serverless PHP runtime does not guarantee that a C compiler or arbitrary executable is available, and separate function invocations do not provide reliable shared writable storage for generated downloads. Therefore this configuration can address PHP request routing, but it does **not** make the full C-based compress/decompress workflow production-ready on Vercel. For full functionality, deploy on a PHP host with GCC/exec enabled, or move the C compression engine to a separate backend service and use persistent/shared storage.

After pushing these changes, redeploy the project on Vercel. If Vercel rejects the `vercel-php@0.9.0` runtime, the community runtime is not enabled/available for your deployment; use a PHP-compatible host instead.
