# Tinkerforge Warp Charger
   Dieses Modul ermöglicht es, einen Warp Charger in IP-Symcon zu integrieren.
     
   ## Inhaltverzeichnis
   1. [Konfiguration](#1-konfiguration)
   2. [Funktionen](#2-funktionen)
   
   ## 1. Konfiguration
   
   Feld | Beschreibung
   ------------ | ----------------
   MQTT Topic | Hier wird das Topic (z.B. warp/USF) des Warp Chargers eingetragen.
   Typ | Hier wird die Hardware-Version des Chargers eingetragen.
   
   ## 2. Funktionen

   ```php
   RequestAction($VariablenID, $Value);
   ```
   Mit dieser Funktion kann das Laden gestartet ($Value = true) oder beendet ($Value = false) werden.
   
   **Beispiel:**
   
   Variable ID Status: 12345
   ```php
   RequestAction(12345, true); // Laden beginnen
   RequestAction(12345, false); // Laden stoppen
   ```