
dbcmp 1.1 (C) 2012-2020 mes3hacklab

dbcmp [ -j ] -c <configFile> -o <outputFile>
	    Salva la struttura del db per l'analisi.

dbcmp -a <file1> -b <file2> [ -s ]
	    Confronta le strutture dei due file.

Parametri:
	-i  Non essere pedante su db e versione.
	-j  Usa il file di configurazione in formato json.
	-s  Ignora l'errore di confronto sullo stesso server.
	-V  Visualizza la versione del programma.

--------------------------------------------------------------------------------
Esempio del file di configurazione:
Trattasi di un file .ini

[db]
db=nomeDatabase
mysql=127.0.0.1
dblog=login
dbpas=password
charset=codifica

Esempio:

[db]
db=database
mysql=127.0.0.1
dblog=root
dbpas=lamer
charset=utf8mb4

--------------------------------------------------------------------------------
Il programma genera un file di output a cui potete mettere come estensione ad
esempio ".dbcmp"

Dopo occorrerà fare un confronto dei due file con lo stesso comando.
Il risultato sarà un file di testo riassuntivo con le differenze tra i due db.
Codifica UTF-8.
