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

<input type="file" id="csvInput" accept=".csv">
<div id="dropZone">Drag & Drop Images Here</div>
<div id="status"></div>

<script>
const dropZone = document.getElementById('dropZone');
const csvInput = document.getElementById('csvInput');
const statusDiv = document.getElementById('status');
const chunkSize = 1024 * 512; // 512 KB
const uploads = {};
const products = [];

// ----------------------------
// CSV Upload
// ----------------------------
csvInput.addEventListener('change', async e => {
    const file = csvInput.files[0];
    if (!file) return;

    const text = await file.text();
    const lines = text.split('\n').filter(l => l.trim() !== '');
    const header = lines[0].split(',').map(h => h.trim());
    const rows = lines.slice(1).map(l => l.split(',').map(c => c.trim()));

    products.length = 0;
    for (let row of rows) {
        const data = {};
        header.forEach((h, i) => data[h] = row[i] || '');
        products.push(data);
        statusDiv.innerHTML += `CSV Row: SKU=${data.sku}, Name=${data.name}<br>`;
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
        const product = products.find(p => p.name.trim().toLowerCase() === file.name.replace(/\.[^/.]+$/, "").trim().toLowerCase());
        if (!product) {
            statusDiv.innerHTML += `No matching CSV row for ${file.name}<br>`;
            continue;
        }

        const imageId = await uploadFile(file);
        if (imageId) {
            await fetch('{{ url("/api/products/attach-image") }}', {
                method: 'POST',
                headers: {'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}'},
                body: JSON.stringify({
                    sku: product.sku,
                    image_id: imageId
                })
            });
            statusDiv.innerHTML += `Linked image ${file.name} to SKU ${product.sku}<br>`;
        }
    }
});

// ----------------------------
// Chunked Upload Function
// ----------------------------
async function uploadFile(file) {
    statusDiv.innerHTML += `Starting upload for ${file.name}...<br>`;

    const startRes = await fetch('{{ url("/api/uploads/start") }}', {
        method: 'POST',
        headers: {'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}'},
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
    for (let i = 0; i < totalChunks; i++) {
        const chunk = file.slice(i * chunkSize, (i + 1) * chunkSize);
        const formData = new FormData();
        formData.append('chunk', chunk);
        formData.append('uuid', uuid);
        formData.append('index', i);

        const res = await fetch('{{ url("/api/uploads/chunk") }}', { method: 'POST', body: formData });
        const data = await res.json();
        statusDiv.innerHTML += `Chunk ${i+1}/${totalChunks} uploaded for ${file.name}<br>`;
        if (data.status !== 'chunk_received') return null;
    }

    const finalizeRes = await fetch('{{ url("/api/uploads/finalize") }}', {
        method: 'POST',
        headers: {'Content-Type': 'application/json', 'X-CSRF-TOKEN': '{{ csrf_token() }}'},
        body: JSON.stringify({uuid})
    });
    const finalizeData = await finalizeRes.json();
    if (finalizeData.status === 'completed') return finalizeData.image_id;
    return null;
}

// ----------------------------
// SHA256 Helper
// ----------------------------
async function sha256(file) {
    const buffer = await file.arrayBuffer();
    const hashBuffer = await crypto.subtle.digest('SHA-256', buffer);
    return Array.from(new Uint8Array(hashBuffer)).map(b => b.toString(16).padStart(2,'0')).join('');
}
</script>

</body>
</html>
