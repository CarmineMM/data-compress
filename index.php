<?php
error_reporting(E_ALL);

if (isset($_POST['action'])) {

    $zip = new \ZipArchive();

    // Comprimir
    if (isset($_POST['folder_compress']) && isset($_POST['file_result'])) {
        $folder_compress = trim(htmlspecialchars($_POST['folder_compress'])); // Carpeta a comprimir
        $file_result = trim(htmlspecialchars($_POST['file_result'])); // Archivo resultante
        $path_to_folder_file = __DIR__ . DIRECTORY_SEPARATOR . $folder_compress;

        // Abrir o crear el archivo destino
        $zip->open($file_result, ZipArchive::CREATE | ZipArchive::OVERWRITE);

        // Comprimir archivo
        if (is_file($path_to_folder_file)) {
            $zip->addFile($path_to_folder_file, $folder_compress);
        }
        // Comprimir capetas
        else {
            $folder = realpath($folder_compress);

            // Ahora usando funciones de recursividad vamos a explorar todo el directorio 
            // y a en listar todos los archivos contenidos en la carpeta
            $files = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($folder),
                \RecursiveIteratorIterator::LEAVES_ONLY
            );

            foreach ($files as $name => $file) {
                if (!$file->isDir()) {
                    $filePath = $file->getRealPath();
                    $relativePath = substr($filePath, strlen($folder) + 1);
                    $zip->addFile($filePath, $relativePath);
                }
            }
        }

        $zip->close();

        echo json_encode([
            'data' => [
                'message'         => $file_result,
                'path_folder'     => $path_to_folder_file,
                'folder_compress' => $folder_compress,
            ],
        ]);
    }

    if (isset($_POST['file_uncompress']) && isset($_POST['folder_result'])) {
        $file = trim(htmlspecialchars($_POST['file_uncompress']));
        $folder_result = trim(htmlspecialchars($_POST['folder_result']));

        if ($zip->open($file) === TRUE) {
            $path = getcwd(); // Directorio actual
            $zip->extractTo($path . DIRECTORY_SEPARATOR . $folder_result); // Extraer en directorio actual con el mismo nombre
            $zip->close();
            echo json_encode([
                'data' => [
                    'message' => $path . DIRECTORY_SEPARATOR . $folder_result,
                ],
            ]);
        }
        // Sin poder descomprimir
        else {
            echo json_encode([
                'errors' => [
                    "No se encuentra el archivo: {$file}",
                ],
            ]);
        }
    }

    die();
}
?>
<!DOCTYPE html>
<html lang="es">

<head>
    <meta charset="UTF-8">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Comprimir/Descomprimir archivo</title>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/skeleton/2.0.4/skeleton.min.css">
    <script src="//unpkg.com/alpinejs" defer></script>
    <style>
        * {
            box-sizing: border-box;
            padding: 0;
            margin: 0;
        }

        body {
            font-size: 16px;
            font-family: Raleway, HelveticaNeue, "Helvetica Neue", Helvetica, Arial, sans-serif;
            color: #555;
        }

        #app {
            min-height: 500px;
            height: 100vh;
            width: 100%;
            display: flex;
            justify-content: center;
            align-items: center;
        }

        form {
            max-width: 500px;
            padding: 2rem;
            border: 1px solid #D1D1D1;
            border-radius: 4px;
        }

        input:read-only {
            background-color: #edebeb;
        }

        .btn-actions {
            display: flex;
            justify-content: space-between;
            gap: 1rem;
        }

        .btn-actions button {
            width: 100%;
        }

        [x-cloak] {
            display: none;
        }

        .text-center {
            text-align: center;
        }

        button:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }

        p {
            overflow-wrap: anywhere;
        }
    </style>
</head>

<body>
    <main id="app" x-data="compressor">
        <section>
            <div class="btn-actions">
                <button @click="action = 'compress'">Comprimir</button>
                <button @click="action = 'uncompress'">Descomprimir</button>
            </div>
            <form method="POST" @submit.prevent="submit">
                <div x-show="action === 'compress'" x-cloak>
                    <label for="folder_compress">Carpeta o archivo a comprimir</label>
                    <input class="u-full-width" type="text" id="folder_compress" x-model="folder">

                    <label for="file_compress">Nombre del archivo o carpeta a comprimir</label>
                    <input class="u-full-width" type="text" id="file_compress" :value="`${folder}.zip`" readonly>

                    <button class="button-primary" type="submit" :disabled="folder.replace('.zip', '').length <= 0">Comprimir</button>
                </div>

                <div x-show="action === 'uncompress'" x-cloak>
                    <label for="folder">Archivo .zip a descomprimir</label>
                    <input class="u-full-width" type="text" id="file_uncompress" x-model="file" @blur="addZip">

                    <label for="folder">Carpeta resultante</label>
                    <input class="u-full-width" type="text" id="folder_result" x-model="folderUncompress">

                    <button class="button-primary" type="submit" :disabled="file.length <= 1">Descomprimir</button>
                </div>

                <div x-show="action === 'download-compress'" x-cloak>
                    <h3 class="text-center">Archivo Comprimido</h3>
                    <p>Ruta: <span x-text="feedback.path_folder"></span></p>
                    <a :href="download" download class="button">Descargar <span x-text="feedback.download"></span></a>
                </div>

                <div x-show="action === 'feedback-uncompress'" x-cloak>
                    <p class="text-center">Archivos descomprimidos en: <span x-text="feedback"></span></p>
                </div>

                <div x-show="action === 'error'" x-cloak>
                    <h1 class="text-center">Opss...</h1>
                    <p x-text="feedback"></p>
                </div>

                <div x-show="action === 'loading'">
                    <h5 class="text-center" style="margin-bottom: 0.2rem;">Espere...</h5>
                </div>
            </form>
        </section>
    </main>

    <script>
        document.addEventListener('alpine:init', () => {
            Alpine.data('compressor', () => ({
                folder: '',
                file: '',
                action: 'loading',
                feedback: '',
                download: '',
                folderUncompress: '',
                init() {
                    this.action = 'compress';

                    this.$watch('file', (e) => {
                        this.folderUncompress = e.replace('.zip', '');
                    });
                },

                addZip() {
                    if (!this.file.includes('.zip')) {
                        this.file = this.file.concat('.', 'zip')
                    }
                },

                async submit() {
                    const action = this.action;
                    this.action = 'loading';

                    try {
                        if (action === 'uncompress') {
                            await this.unCompress(action);
                            return;
                        }

                        await this.compress(action);
                    } catch (e) {
                        this.action = 'error';
                        this.feedback = e;
                    }
                },

                async compress(action) {
                    const data = new FormData();

                    data.append('folder_compress', this.folder);
                    data.append('file_result', `${this.folder}.zip`);
                    data.append('action', action);

                    const result = await fetch(location.href, {
                        method: 'POST',
                        headers: {
                            Accept: 'application/json'
                        },
                        body: data,
                    });

                    const {
                        data: response
                    } = await result.json();

                    this.feedback = {
                        path_folder: response.path_folder,
                        download: response.message,
                    };

                    this.download = location.href + '/' + response.message;

                    this.action = 'download-compress';
                },

                async unCompress(action) {
                    const data = new FormData();

                    data.append('file_uncompress', this.file);
                    data.append('folder_result', this.folderUncompress);
                    data.append('action', action);

                    const result = await fetch(location.href, {
                        method: 'POST',
                        headers: {
                            Accept: 'application/json'
                        },
                        body: data,
                    });

                    const response = await result.json();

                    if (response.errors) {
                        this.action = 'error';
                        this.feedback = response.errors[0];
                        return;
                    }

                    this.feedback = response.data.message;

                    this.action = 'feedback-uncompress';
                },
            }));
        });
    </script>
</body>

</html>