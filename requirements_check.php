<?php
/**
 * WebyMail - Controllo Requisiti di Sistema
 * Questo file verifica la presenza delle estensioni PHP necessarie prima dell'avvio.
 * In caso di mancanza, mostra una pagina di avviso chiara invece di un Errore 500.
 */

$missingExtensions = [];

// Verifica estensione IMAP (fondamentale per la posta)
if (!extension_loaded('imap')) {
    $missingExtensions[] = 'imap';
}

// Verifica estensione SQLite (fondamentale per il database locale)
if (!extension_loaded('sqlite3') && !in_array('sqlite', class_exists('PDO') ? PDO::getAvailableDrivers() : [])) {
    $missingExtensions[] = 'sqlite3 (o PDO_SQLITE)';
}

// Se manca qualcosa, blocca l'esecuzione e mostra l'avviso
if (!empty($missingExtensions)) {
    $phpVersion = phpversion();
    $extensionsList = implode(', ', $missingExtensions);
    
    // Genera il comando di installazione suggerito per Ubuntu/Debian
    $phpMajorMinor = substr($phpVersion, 0, 3); // es. "8.5"
    $installCommand = "sudo apt update && sudo apt install php{$phpMajorMinor}-imap php{$phpMajorMinor}-sqlite3";
    
    header('HTTP/1.1 503 Service Unavailable');
    header('Content-Type: text/html; charset=UTF-8');
    ?>
    <!DOCTYPE html>
    <html lang="it">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <title>WebyMail - Requisiti di Sistema Mancanti</title>
        <style>
            body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Helvetica, Arial, sans-serif; background-color: #f4f6f8; color: #333; display: flex; align-items: center; justify-content: center; min-height: 100vh; margin: 0; padding: 20px; }
            .container { background: #fff; border-radius: 8px; box-shadow: 0 4px 12px rgba(0,0,0,0.1); max-width: 600px; width: 100%; padding: 30px; border-left: 5px solid #e74c3c; }
            h1 { color: #e74c3c; margin-top: 0; font-size: 24px; }
            p { line-height: 1.6; color: #555; }
            code { background: #f0f0f0; padding: 2px 6px; border-radius: 4px; font-family: "SFMono-Regular", Consolas, "Liberation Mono", Menlo, monospace; color: #c0392b; }
            .command { background: #2c3e50; color: #ecf0f1; padding: 15px; border-radius: 6px; overflow-x: auto; margin: 15px 0; font-family: monospace; }
            .note { font-size: 0.9em; color: #7f8c8d; margin-top: 20px; border-top: 1px solid #eee; padding-top: 15px; }
        </style>
    </head>
    <body>
        <div class="container">
            <h1>⚠️ Requisiti di Sistema Mancanti</h1>
            <p>WebyMail non può avviarsi perché mancano una o più estensioni PHP necessarie sul server:</p>
            <p><strong>Estensioni mancanti:</strong> <code><?php echo htmlspecialchars($extensionsList); ?></code></p>
            <p>Per risolvere il problema, è necessario installare queste estensioni. Se hai accesso al terminale del server (Ubuntu/Debian), esegui questo comando:</p>
            <div class="command"><?php echo htmlspecialchars($installCommand); ?></div>
            <p>Dopo l'installazione, riavvia il server web (es. <code>sudo systemctl restart apache2</code>).</p>
            <p class="note">Se non hai accesso diretto al server, inoltra questo messaggio al tuo amministratore di sistema o al supporto del tuo hosting.</p>
        </div>
    </body>
    </html>
    <?php
    exit; // Ferma l'esecuzione qui, evitando l'Errore 500
}
