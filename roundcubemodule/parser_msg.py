import os
import sys
import extract_msg
import json

def main():
    if len(sys.argv) < 2:
        print("Usage: python parser_msg.py <chemin_du_fichier_msg>")
        sys.exit(1)

    msg_file = sys.argv[1]

    # Charger le message .msg
    msg = extract_msg.Message(msg_file)

    # Créer dossier attachments s'il n'existe pas
    attachments_dir = os.path.join(os.path.dirname(__file__), 'attachments')
    os.makedirs(attachments_dir, exist_ok=True)

    attachments_list = []

    # Parcourir et sauvegarder les pièces jointes
    for attachment in msg.attachments:
        filename = attachment.longFilename or attachment.shortFilename or 'unknown.bin'
        # Sauvegarder dans le dossier attachments
        attachment.save(customPath=attachments_dir)

        attachments_list.append({
            'filename': filename,
            'saved_path': os.path.join(attachments_dir, filename)
        })

    # Préparer les données à renvoyer
    data = {
        "subject": msg.subject,
        "sender": msg.sender,
        "to": msg.to,
        "date": str(msg.date),
        "body": msg.body,
        "attachments": attachments_list
    }

    # Écrire le JSON sur la sortie standard en UTF-8
    sys.stdout.buffer.write(json.dumps(data, ensure_ascii=False).encode('utf-8'))

if __name__ == "__main__":
    main()
