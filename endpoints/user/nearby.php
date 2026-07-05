<?php
// Deprecated — nearby attractions now fetched client-side directly from Overpass API.
http_response_code(410);
echo json_encode(['success' => false, 'message' => 'deprecated']);