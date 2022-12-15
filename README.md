# Egea transfer procedures
Procedure di trasferimento foto e file excel di Egea utilizzando un server ponte di interscambio.
Per far funzionare queste procedure è indispensabile avere una copia della cartella Protected, con tutti i suoi files contenuti.
Si raccomanda la visione dell'allegato tecnico per informazioni approfondite.

## Tecnologia
- Applicativo v1.3.2
- Php v8.1
- Mysql server v8.0.31
- Php nativo, nessun framework utilizzato
- Dipendenze da alcune librerie, come guzzlehttp, phpmailer, symfony ed altri
- Collegamento SMB con macchina condivisa montando un percorso sulla macchina ponte

## Installazione
Per installare questa procedura eseguire questi passaggi:<br>
- git clone https://github.com/lektor-srl/egea-transfer-procedures.git
- composer install
- Copiarsi tutta la cartella Protected all'interno della cartella principale dalle fonti sicure. 
- Modificare le configurazioni all'interno del file Environment.php

## Utilizzo
Questo sistema viene utilizzato per 2 casistiche, entrambe schedulate tramite cron:
- Download delle foto dal bucket di Google e relativo upload nello spazio Ftp del cliente
  - php Jobs/attachmentsProcedure --mode=download
  - php Jobs/attachmentsProcedure --mode=upload
- Download dei tracciati csv dallo spazio Ftp del cliente verso la macchina condivisa del cliente e viceversa
  - php Jobs/flowsProcedure --mode=download
  - php Jobs/flowsProcedure --mode=upload 
    
## Logging
Questo processo supporta i seguenti metodi di logging configurabili ed attivabili nel file Environment:
- Console 
- Filesystem, Y-m-d.txt 
- Database, sul server locale nella tabella activity_log
- Mail, in caso di errori

## Dettaglio funzionamento
### Attachments Procedure, download mode
Questo processo si occupa di prelevare, per ogni ente configurato nell'environment sotto la voce utilities, tutte le foto delle lavorazione.
<br>Questo processo cerca in modo ricorsivo tutti gli oggetti con il prefisso "lav_".
<br>Prende in considerazione solo gli oggetti con queste caratteristiche:
- contentType = image/jpeg (prende solo le immagini)
- updated == data corrente (prende solo gli oggetti creati nel giorno corrente)

Una volta scaricate le immagini, vengono salvate sul web server ponte nella cartella data/attachments/{ente}/

### Attachments Procedure, upload mode
Questo processo si occupa di prendere le foto scaricate precedentemente e fare un upload sullo spazio Ftp messo a disposizione dal cliente sotto la cartella {ente}/IMG/UP/
<br>Viene caricata l'intera cartella contenente le foto e non individualmente per questioni di prestazioni.

### Flows Procedure, download mode
Questo processo si occupa di scaricare dallo spazio Ftp sotto la cartella {ente}/LET/DW i tracciati in csv delle letture e depositarli nella macchina condivisa per essere elaborati da Gea sotto la cartella IN/{ente}Acqua Massive/.
<br>Vengono scaricati solo i tracciati che non sono stati già scaricati. Per effettuare questo controllo, viene interrogata la tabella flussi_file (sul DB di google) e si verifica che il tracciato che si sta tentando di scaricare non sia già presente
<br>Una volta depositati questi files, Gea li lavorerà andando ad impostare sul DB di Google il campo flag_pronto_per_esportazione = 1

### Flows Procedure, upload mode
Questo processo si occupa di verificare quali tracciati sono stati elaborati da Gea, e caricarli nello spazio Ftp del cliente sotto la cartella {sede}/LET/UP
<br>Per sapere quali tracciati sono stati elaborati si interroga la tabella flussi_file verificando il campo flag_pronto_per_esportazione = 1 (sul DB di Google)
