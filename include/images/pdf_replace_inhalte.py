import sys

# pdftk orginal.pdf output in.pdf drop_xfa drop_xmp uncompress need_appearances

# Datei kann als Argument übergeben werden, sonst Standardpfad
pdf_path = sys.argv[1] if len(sys.argv) > 1 else "in.pdf"


new = {
    "kursbegin": "01.01.2028",
    "kursende": "31.12.2028",
    "prüfungstermin": "01.01.2029",
    "geburtsdatum": "25.12.2000",
    "name": "Max Mustermann",
    "strasse": "Musterstraße 1",
    "plz_ort": "12345 Musterstadt",
    "telefon": "0123456789",
    "email": "max.mustermann@example.com",
    "name_erz": "Erzname Mustermann",
    "strasse_erz": "Erzstraße 2",
    "plz_ort_erz": "54321 Erzstadt",
    "telefon_erz": "9876543210",
    "email_erz": "erzname.mustermann@example.com",
}


def substitute_fields(new_values):
    substitutions = {
        "kursbegin": b"xx.xx.xxxx",
        "kursende": b"yy.yy.yyyy",
        "prüfungstermin": b"zz.zz.zzzz...........",
        "geburtsdatum": b"gg.gg.gggg........................................",
        "name": b"nnnnnnnnnnnnnnnnnnnnnnnnnnnnnnnnnn",
        "strasse": b"sssssssssssssssssssssssssssssssssss",
        "plz_ort": b"plzplzplzplzplzplzplzplzplzplzplpplzplzplz",
        "telefon": b"99999999999999999999999999999999",
        "email": b"email@nnnnnnnnnnnnnnnnnnnnnnnnnnnnnnn.de",
        "name_erz": b"n2nnnnnnnnnnnnnnnnnnnnnnnnnnnnn",
        "strasse_erz": b"s2sssssssssssssssssssssssssssssss",
        "plz_ort_erz": b"plz2plzplzplzplzplzplzplzplzplzplzplz",
        "telefon_erz": b"888888888888888888888888888",
        "email_erz": b"email2@nnnnnnnnnnnnnnnnnnnnnnnnnnnnnnn.de",
    }

    with open(pdf_path, "rb") as f:
        content = f.read()

    for k, v in substitutions.items():
        vnew = new_values[k].encode(
            "iso-8859-1"
        )  # Neue Werte als Bytes, passend zum PDF-Encoding
        # vnew auf die Länge von v bringen (auffüllen oder abschneiden)
        vnew = vnew.ljust(len(v))[: len(v)]

        content = content.replace(v, vnew)

    with open("output.pdf", "wb") as f:
        f.write(content)


substitute_fields(new)
