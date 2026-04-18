function escapeHtml(value) {
  return String(value ?? '')
    .replaceAll('&', '&amp;')
    .replaceAll('<', '&lt;')
    .replaceAll('>', '&gt;')
    .replaceAll('"', '&quot;')
    .replaceAll("'", '&#39;');
}

function buildRow(label, value) {
  return `
    <div class="passport-withdrawal-print-row">
      <div class="passport-withdrawal-print-cell passport-withdrawal-print-cell-label">${escapeHtml(label)}</div>
      <div class="passport-withdrawal-print-cell passport-withdrawal-print-cell-value">${escapeHtml(value || '')}</div>
    </div>
  `;
}

export function printRejoiningReport(form) {
  const printWindow = window.open('', '_blank', 'width=1100,height=900');
  if (!printWindow) {
    return false;
  }

  const html = `
    <!doctype html>
    <html>
      <head>
        <meta charset="utf-8" />
        <title>Rejoining Report</title>
        <style>
          @page {
            size: A4 portrait;
            margin: 8mm;
          }

          :root {
            --accent: #f26430;
            --accent-soft: #fff1eb;
            --ink: #111827;
            --muted: #6b7280;
            --line: #d7deea;
            --panel: #f8fafc;
          }

          * {
            box-sizing: border-box;
          }

          body {
            margin: 0;
            font-family: Arial, Helvetica, sans-serif;
            background: #eef2f7;
            color: var(--ink);
          }
          .page {
            width: 194mm;
            min-height: 279mm;
            margin: 0 auto;
            padding: 12mm 10mm;
            background: #fff;
            border: 1px solid var(--line);
            box-shadow: 0 20px 45px rgba(15, 23, 42, 0.08);
            overflow: hidden;
          }
          .header {
            display: grid;
            grid-template-columns: 152px 1fr 158px;
            gap: 16px;
            align-items: center;
            padding-bottom: 12px;
            border-bottom: 2px solid var(--line);
          }
          .logo {
            display: flex;
            align-items: center;
            justify-content: center;
            min-height: 102px;
            padding: 10px;
            background: #fff;
            border: 1px solid var(--line);
          }
          .logo img {
            max-width: 128px;
            max-height: 102px;
            object-fit: contain;
          }
          .heading {
            text-align: left;
          }
          .heading h1 {
            margin: 0;
            font-size: 24px;
            font-weight: 800;
            letter-spacing: 0.02em;
          }
          .heading h2 {
            margin: 8px 0 0;
            font-size: 15px;
            font-weight: 700;
            color: var(--muted);
          }
          .meta {
            min-width: 0;
            padding: 12px 14px;
            background: var(--panel);
            border: 1px solid var(--line);
          }
          .meta span {
            display: block;
            font-size: 11px;
            font-weight: 700;
            color: var(--muted);
            letter-spacing: 0.08em;
            text-transform: uppercase;
          }
          .meta strong {
            display: block;
            margin-top: 6px;
            font-size: 18px;
            font-weight: 800;
          }
          .summary {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 10px;
            margin-top: 14px;
          }
          .summary-card {
            padding: 12px 14px;
            border: 1px solid var(--line);
            background: var(--panel);
          }
          .summary-card span {
            display: block;
            font-size: 11px;
            font-weight: 700;
            color: var(--muted);
            letter-spacing: 0.08em;
            text-transform: uppercase;
          }
          .summary-card strong {
            display: block;
            margin-top: 6px;
            font-size: 15px;
            font-weight: 800;
          }
          .section {
            margin-top: 12px;
            border: 1px solid var(--line);
            overflow: hidden;
            page-break-inside: avoid;
          }
          .section-head {
            padding: 10px 14px;
            background: linear-gradient(90deg, var(--accent-soft), #fff);
            border-bottom: 1px solid var(--line);
          }
          .section-head strong {
            font-size: 12px;
            font-weight: 800;
            letter-spacing: 0.04em;
            text-transform: uppercase;
          }
          .passport-withdrawal-print-row {
            display: grid;
            grid-template-columns: 250px 1fr;
          }
          .passport-withdrawal-print-cell {
            min-height: 40px;
            padding: 10px 14px;
            border-bottom: 1px solid var(--line);
          }
          .passport-withdrawal-print-row:last-child .passport-withdrawal-print-cell {
            border-bottom: none;
          }
          .passport-withdrawal-print-cell-label {
            background: #fbfcfe;
            font-size: 11px;
            font-weight: 800;
            color: var(--muted);
            letter-spacing: 0.05em;
            text-transform: uppercase;
            border-right: 1px solid var(--line);
          }
          .passport-withdrawal-print-cell-value {
            font-size: 15px;
            font-weight: 800;
          }
          .signatures {
            display: grid;
            grid-template-columns: repeat(2, minmax(0, 1fr));
            gap: 10px;
            margin-top: 12px;
            page-break-inside: avoid;
          }
          .signature-card {
            display: flex;
            flex-direction: column;
            justify-content: flex-end;
            min-height: 96px;
            padding: 12px 14px 14px;
            border: 1px solid var(--line);
            background: var(--panel);
          }
          .signature-card span {
            display: block;
            font-size: 11px;
            font-weight: 700;
            color: var(--muted);
            letter-spacing: 0.08em;
            text-transform: uppercase;
          }
          .signature-card strong {
            display: block;
            margin-top: auto;
            padding-top: 10px;
            border-top: 2px solid #b8c2d1;
            font-size: 15px;
            font-weight: 800;
          }
          .approvals {
            margin-top: 10px;
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 10px;
            page-break-inside: avoid;
          }
          .approval-card {
            min-height: 116px;
            padding: 10px 12px 12px;
            border: 1px solid var(--line);
            background: #fff;
            text-align: center;
            display: flex;
            align-items: flex-end;
          }
          .approval-card strong {
            display: block;
            width: 100%;
            padding-top: 10px;
            border-top: 2px solid #111827;
            font-size: 13px;
            font-weight: 800;
            line-height: 1.2;
          }

          @media print {
            body {
              background: #fff;
            }

            .page {
              width: auto;
              min-height: auto;
              margin: 0;
              padding: 0;
              border: none;
              box-shadow: none;
            }
          }
        </style>
      </head>
      <body>
        <div class="page">
          <div class="header">
            <div class="logo">
              <img src="${escapeHtml(form.logo_url || '')}" alt="Company Logo" />
            </div>
            <div class="heading">
              <h1>REJOINING REPORT</h1>
              <h2>MEDIA GROUP OF COMPANIES</h2>
            </div>
            <div class="meta">
              <span>Form Date</span>
              <strong>${escapeHtml(form.form_date_label || form.form_date || '')}</strong>
            </div>
          </div>

          <div class="summary">
            <div class="summary-card">
              <span>Employee</span>
              <strong>${escapeHtml(form.employee_name || '-')}</strong>
            </div>
            <div class="summary-card">
              <span>Date Of Rejoin</span>
              <strong>${escapeHtml(form.rejoin_date_label || form.rejoin_date || '-')}</strong>
            </div>
          </div>

          <div class="section">
            <div class="section-head"><strong>Rejoining Details</strong></div>
            ${buildRow('PASSPORT RECEIVED AT HEAD OFFICE', form.passport_received_at_head_office)}
            ${buildRow('NAME OF THE EMPLOYEE', form.employee_name)}
            ${buildRow('DESIGNATION', form.designation)}
            ${buildRow('NATIONALITY', form.nationality)}
            ${buildRow('CONTACT NO.', form.contact_no)}
            ${buildRow('PASSPORT NO.', form.passport_number)}
            ${buildRow('VACATION START DATE', form.vacation_start_date_label || form.vacation_start_date)}
            ${buildRow('VACATION END DATE', form.vacation_end_date_label || form.vacation_end_date)}
            ${buildRow('DATE OF REJOIN', form.rejoin_date_label || form.rejoin_date)}
          </div>

          <div class="signatures">
            <div class="signature-card">
              <span>Employee Signature</span>
              <strong aria-hidden="true"></strong>
            </div>
            <div class="signature-card">
              <span>Company Manager Signature</span>
              <strong aria-hidden="true"></strong>
            </div>
          </div>

          <div class="approvals">
            <div class="approval-card">
              <strong>Approved By<br />General Manager</strong>
            </div>
            <div class="approval-card">
              <strong>Approved By<br />Managing Director</strong>
            </div>
          </div>
        </div>
      </body>
    </html>
  `;

  printWindow.document.open();
  printWindow.document.write(html);
  printWindow.document.close();

  let printed = false;
  const triggerPrint = () => {
    if (printed) {
      return;
    }

    printed = true;
    printWindow.focus();
    printWindow.print();
  };

  const images = Array.from(printWindow.document.images);
  const pendingImages = images.filter((image) => !image.complete);

  if (pendingImages.length === 0) {
    setTimeout(triggerPrint, 250);
  } else {
    let remaining = pendingImages.length;
    const finishImage = () => {
      remaining -= 1;
      if (remaining <= 0) {
        setTimeout(triggerPrint, 150);
      }
    };

    pendingImages.forEach((image) => {
      image.addEventListener('load', finishImage, { once: true });
      image.addEventListener('error', finishImage, { once: true });
    });

    setTimeout(triggerPrint, 1200);
  }

  return true;
}
