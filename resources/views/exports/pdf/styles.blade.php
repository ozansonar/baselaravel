{{-- Saf CSS: mPDF'e HEADER_CSS kipinde ayrıca yükleniyor. Gövdeyle birlikte
     gönderilirse stil uygulanmaz, kuralların kendisi sayfaya metin olarak
     basılır. Altbilgi de bu kuralları görsün diye gövdeden önce yüklenir. --}}
    body {
        font-family: dejavusanscondensed, sans-serif;
        font-size: 7.5pt;
        color: #1b1b1b;
    }

    .doc-head {
        border-bottom: 0.6pt solid #444;
        padding-bottom: 3pt;
        margin-bottom: 6pt;
    }

    .doc-title {
        font-size: 12pt;
        font-weight: bold;
        margin: 0;
    }

    .doc-meta {
        font-size: 7pt;
        color: #555;
        margin: 2pt 0 0 0;
    }

    table.doc-table {
        width: 100%;
        border-collapse: collapse;
    }

    table.doc-table thead th {
        background-color: #ececec;
        border: 0.4pt solid #9a9a9a;
        padding: 3pt 4pt;
        font-weight: bold;
        text-align: left;
    }

    table.doc-table td {
        border: 0.4pt solid #c4c4c4;
        padding: 2.5pt 4pt;
        /* Uzun metin sütunu taşırmasın: hücre içinde kırılır. */
        word-wrap: break-word;
        overflow-wrap: break-word;
    }

    table.doc-table td.num {
        text-align: right;
    }

    table.doc-table tbody tr:nth-child(even) td {
        background-color: #f7f7f7;
    }

    .doc-footer {
        border-top: 0.4pt solid #bbb;
        padding-top: 2pt;
        font-size: 6.5pt;
        color: #555;
    }

    .doc-footer td.right {
        text-align: right;
    }

    .doc-empty {
        padding: 12pt 0;
        font-size: 9pt;
        color: #666;
    }
