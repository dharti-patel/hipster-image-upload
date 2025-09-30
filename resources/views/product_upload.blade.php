<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<title>CSV + Image Upload</title>
<style>
body { font-family: Arial, sans-serif; margin: 40px; }
#dropZone {
    width: 400px; height: 200px;
    border: 2px dashed #2fb1fc; border-radius: 10px;
    display: flex; align-items: center; justify-content: center;
    color: #555; margin-bottom: 20px;
}
#dropZone.hover { background-color: #e6f7ff; }
#status { margin-top: 20px; }
</style>
</head>
<body>

<h2>CSV + Image Upload</h2>

<form id="csvForm" action="{{ url('/products/import-csv') }}" method="POST" enctype="multipart/form-data">
    @csrf
    <input type="file" name="csv_file" id="csvInput" accept=".csv" required>
    <button type="submit">Upload CSV</button>
</form>

<div id="dropZone">Drag & Drop Images Here</div>
<div id="status"></div>

<meta name="csrf-token" content="{{ csrf_token() }}">
<meta name="attach-image-url" content="{{ url('/api/products/attach-image') }}">
<meta name="uploads-start-url" content="{{ url('/api/uploads/start') }}">
<meta name="uploads-chunk-url" content="{{ url('/api/uploads/chunk') }}">
<meta name="uploads-finalize-url" content="{{ url('/api/uploads/finalize') }}">

<script>
const dropZone = document.getElementById('dropZone');
const csvInput = document.getElementById('csvInput');
const statusDiv = document.getElementById('status');
const csrfToken = document.querySelector('meta[name="csrf-token"]').getAttribute('content');
const attachImageUrl = document.querySelector('meta[name="attach-image-url"]').getAttribute('content');
const startUrl = document.querySelector('meta[name="uploads-start-url"]').getAttribute('content');
const chunkUrl = document.querySelector('meta[name="uploads-chunk-url"]').getAttribute('content');
const finalizeUrl = document.querySelector('meta[name="uploads-finalize-url"]').getAttribute('content');

const chunkSize = 1024 * 512; // 512 KB
const uploads = {};
let products = [];

// ----------------------------
// CSV Form Submission
// ----------------------------
document.getElementById('csvForm').addEventListener('submit', async e => {
    e.preventDefault();
    const formData = new FormData(e.target);
    formData.append('_token', csrfToken); // ensure CSRF token is included

    try {
        const res = await fetch(e.target.action, {
            method: 'POST',
            body: formData
        });

        const text = await res.text();
        let data;
        try {
            data = JSON.parse(text);
        } catch(err) {
            statusDiv.innerHTML += 'CSV import failed (server returned HTML)<br>';
            console.error('Server returned HTML instead of JSON:', text);
            return;
        }

        if(data.products && data.summary){
            products = data.products;
            statusDiv.innerHTML += `CSV imported: ${data.summary.total} rows<br>`;
        } else {
            statusDiv.innerHTML += 'CSV import failed<br>';
        }

    } catch(err) {
        statusDiv.innerHTML += 'CSV import failed (network error)<br>';
        console.error(err);
    }
});

// ----------------------------
// Drag & Drop Image Upload
// ----------------------------
dropZone.addEventListener('dragover', e => { e.preventDefault(); dropZone.classList.add('hover'); });
dropZone.addEventListener('dragleave', e => { e.preventDefault(); dropZone.classList.remove('hover'); });
dropZone.addEventListener('drop', async e => {
    e.preventDefault();
    dropZone.classList.remove('hover');
    const files = e.dataTransfer.files;

    for (let file of files) {
        const product = products.find(p => p.sku && p.sku.trim().toLowerCase() === file.name.replace(/\.[^/.]+$/, "").trim().toLowerCase());
        if(!product){
            statusDiv.innerHTML += `No matching CSV row for ${file.name}<br>`;
            continue;
        }

        const imageId = await uploadFile(file);
        if(imageId){
            try {
                const res = await fetch(attachImageUrl, {
                    method: 'POST',
                    headers: {'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken},
                    body: JSON.stringify({ sku: product.sku, image_id: imageId })
                });
                const data = await res.json();
                if(data.status === 'linked'){
                    statusDiv.innerHTML += `Linked image ${file.name} to SKU ${product.sku}<br>`;
                } else {
                    statusDiv.innerHTML += `Failed to link image ${file.name}<br>`;
                }
            } catch(err){
                console.error(err);
                statusDiv.innerHTML += `Failed to link image ${file.name} (network error)<br>`;
            }
        }
    }
});

// ----------------------------
// Chunked Upload Function
// ----------------------------
async function uploadFile(file){
    statusDiv.innerHTML += `Starting upload for ${file.name}...<br>`;

    const startRes = await fetch(startUrl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken },
        body: JSON.stringify({
            filename: file.name,
            size: file.size,
            mime: file.type,
            checksum: await sha256(file)
        })
    });
    const startData = await startRes.json();
    const uuid = startData.uuid;
    uploads[file.name] = uuid;

    const totalChunks = Math.ceil(file.size / chunkSize);
    for(let i=0; i<totalChunks; i++){
        const chunk = file.slice(i*chunkSize, (i+1)*chunkSize);
        const formData = new FormData();
        formData.append('chunk', chunk);
        formData.append('uuid', uuid);
        formData.append('index', i);

        const res = await fetch(chunkUrl, { method: 'POST', body: formData });
        const data = await res.json();
        statusDiv.innerHTML += `Chunk ${i+1}/${totalChunks} uploaded for ${file.name}<br>`;
        if(data.status !== 'chunk_received') return null;
    }

    const finalizeRes = await fetch(finalizeUrl, {
        method: 'POST',
        headers: { 'Content-Type': 'application/json', 'X-CSRF-TOKEN': csrfToken },
        body: JSON.stringify({ uuid })
    });
    const finalizeData = await finalizeRes.json();
    if(finalizeData.status === 'completed') return finalizeData.image_id;
    return null;
}

// ----------------------------
// SHA256 Helper
// ----------------------------
async function sha256(file){
    const buffer = await file.arrayBuffer();
    const hashBuffer = await crypto.subtle.digest('SHA-256', buffer);
    return Array.from(new Uint8Array(hashBuffer)).map(b=>b.toString(16).padStart(2,'0')).join('');
}
</script>

</body>
</html>
