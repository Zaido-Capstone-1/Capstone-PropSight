<?php
/**
 * includes/_report_generate_modal.php
 * Shared "Generate Report" modal markup for the admin report pages.
 * Include after setting: $repgen_title (string), and the page must load
 * assets/css/admin-css/report-generate.css + assets/js/admin/report-generate.js.
 */
$repgen_title = $repgen_title ?? 'Report';
?>
<div class="repgen-overlay" id="repgenOverlay">
  <div class="repgen-modal">
    <button class="repgen-close" id="repgenCloseBtn" type="button" aria-label="Close">&times;</button>
    <h3>Generate <?php echo htmlspecialchars($repgen_title); ?></h3>
    <p class="repgen-sub">Choose a date range and file format to generate a downloadable report.</p>

    <div class="repgen-presets">
      <button type="button" class="repgen-preset" data-preset="7">Last 7 days</button>
      <button type="button" class="repgen-preset" data-preset="30">Last 30 days</button>
      <button type="button" class="repgen-preset active" data-preset="month">This month</button>
      <button type="button" class="repgen-preset" data-preset="year">This year</button>
    </div>

    <div class="repgen-row">
      <div class="repgen-field">
        <label for="repgenFrom">From</label>
        <input type="date" id="repgenFrom">
      </div>
      <div class="repgen-field">
        <label for="repgenTo">To</label>
        <input type="date" id="repgenTo">
      </div>
    </div>

    <div class="repgen-field">
      <label>Format</label>
      <div class="repgen-formats">
        <div class="repgen-format active" data-format="pdf">
          <div class="repgen-format-icon"><svg xmlns="http://www.w3.org/2000/svg"
              xmlns:xlink="http://www.w3.org/1999/xlink" aria-hidden="true" role="img" width="40" height="50"
              viewBox="0 0 24 24" style="color: rgb(74, 85, 101); opacity: 1; transform: rotate(0deg);">
              <path fill="#ef5350"
                d="M13 9h5.5L13 3.5zM6 2h8l6 6v12a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2m4.93 10.44c.41.9.93 1.64 1.53 2.15l.41.32c-.87.16-2.07.44-3.34.93l-.11.04l.5-1.04c.45-.87.78-1.66 1.01-2.4m6.48 3.81c.18-.18.27-.41.28-.66c.03-.2-.02-.39-.12-.55c-.29-.47-1.04-.69-2.28-.69l-1.29.07l-.87-.58c-.63-.52-1.2-1.43-1.6-2.56l.04-.14c.33-1.33.64-2.94-.02-3.6a.85.85 0 0 0-.61-.24h-.24c-.37 0-.7.39-.79.77c-.37 1.33-.15 2.06.22 3.27v.01c-.25.88-.57 1.9-1.08 2.93l-.96 1.8l-.89.49c-1.2.75-1.77 1.59-1.88 2.12c-.04.19-.02.36.05.54l.03.05l.48.31l.44.11c.81 0 1.73-.95 2.97-3.07l.18-.07c1.03-.33 2.31-.56 4.03-.75c1.03.51 2.24.74 3 .74c.44 0 .74-.11.91-.3m-.41-.71l.09.11c-.01.1-.04.11-.09.13h-.04l-.19.02c-.46 0-1.17-.19-1.9-.51c.09-.1.13-.1.23-.1c1.4 0 1.8.25 1.9.35M7.83 17c-.65 1.19-1.24 1.85-1.69 2c.05-.38.5-1.04 1.21-1.69zm3.02-6.91c-.23-.9-.24-1.63-.07-2.05l.07-.12l.15.05c.17.24.19.56.09 1.1l-.03.16l-.16.82z">
              </path>
            </svg></div>
          <div class="repgen-format-label">PDF</div>
          <div class="repgen-format-desc">Summary report</div>
        </div>
        <div class="repgen-format" data-format="xlsx">
          <div class="repgen-format-icon"><svg xmlns="http://www.w3.org/2000/svg"
              xmlns:xlink="http://www.w3.org/1999/xlink" aria-hidden="true" role="img" width="40" height="50"
              viewBox="0 0 512 512" style="color: rgb(74, 85, 101); opacity: 1; transform: rotate(0deg);">
              <linearGradient id="iconifyReact354" x1="256" x2="256" y1="14.332" y2="497.668"
                gradientUnits="userSpaceOnUse">
                <stop offset="0" stop-color="#1d6b40"></stop>
                <stop offset="1" stop-color="#217748"></stop>
              </linearGradient>
              <path fill="url(#iconifyReact354)"
                d="M491.3 61.6H291.2V14.3L0 62.7v386.7l291.3 48.3v-47.3h200.1c11.4 0 20.7-9.2 20.7-20.6V82.2c-.1-11.5-9.3-20.6-20.8-20.6m4 371.5h-204v-32.7h68.6v-44.1h-68.6v-17h68.6v-44.1h-68.6V278h68.6v-44h-68.6v-17.1h68.6v-44.1h-68.6v-15.9h68.6v-44.1h-68.6V78.6h204zm-36.4-276.3h-81.6v-44.1h81.6zm0 60.2h-81.6v-44.1h81.6zm0 122.8h-81.6v-44.1h81.6zm0-61.8h-81.6v-44h81.6zm0 122.4h-81.6v-44.1h81.6z">
              </path>
              <path fill="#fff"
                d="M291.3 400.4v32.7h204V78.6h-204v34.1h68.6v44.1h-68.6v15.9h68.6v44.1h-68.6V234h68.6v44h-68.6v17.2h68.6v44.1h-68.6v17h68.6v44.1zm85.9-287.7h81.6v44.1h-81.6zm0 60.2h81.6V217h-81.6zm0 61.1h81.6v44h-81.6zm0 61.7h81.6v44.1h-81.6zm0 60.7h81.6v44.1h-81.6zM114 175.2l20.2 52.4c1.5 3.1 2.1 6.4 2.4 8.7c-.2-2.6.4-4.6 1.9-8.7l23.9-55.3l36.7-2.2q-22.35 42.45-42.9 85.8l44.7 88.3l-39.6-2.4l-24.1-57.6c-1.9-4.3-1.5-4.9-1.9-7.1c-.2 1.5-.2 2.8-1.2 5.5l-24.2 56.1l-34-2l39.2-79.7l-35.3-79.9z">
              </path>
            </svg></div>
          <div class="repgen-format-label">Excel</div>
          <div class="repgen-format-desc">Full data</div>
        </div>
        <div class="repgen-format" data-format="csv">
          <div class="repgen-format-icon"><svg xmlns="http://www.w3.org/2000/svg"
              xmlns:xlink="http://www.w3.org/1999/xlink" aria-hidden="true" role="img" width="40" height="50"
              viewBox="0 0 15 15" style="color: rgb(58, 118, 245); opacity: 1; transform: rotate(0deg);">
              <path fill="currentColor" fill-rule="evenodd"
                d="M1 1.5A1.5 1.5 0 0 1 2.5 0h8.207L14 3.293V13.5a1.5 1.5 0 0 1-1.5 1.5h-10A1.5 1.5 0 0 1 1 13.5zM2 6h3v1H3v3h2v1H2zm7 0H6v3h2v1H6v1h3V8H7V7h2zm2 0h-1v3.707l1.5 1.5l1.5-1.5V6h-1v3.293l-.5.5l-.5-.5z"
                clip-rule="evenodd"></path>
            </svg></div>
          <div class="repgen-format-label">CSV</div>
          <div class="repgen-format-desc">Full data</div>
        </div>
      </div>
    </div>

    <div class="repgen-error" id="repgenError"></div>

    <div class="repgen-actions">
      <button type="button" class="btn btn-secondary" id="repgenCancelBtn">Cancel</button>
      <button type="button" class="btn btn-primary repgen-generate-btn" id="repgenGenerateBtn">Generate</button>
    </div>
  </div>
</div>