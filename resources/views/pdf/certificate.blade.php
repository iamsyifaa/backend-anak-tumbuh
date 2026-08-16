<!DOCTYPE html>
<html>
<head><style>
    body { font-family: sans-serif; text-align: center; padding-top: 100px; }
    h1 { font-size: 32px; }
    .name { font-size: 28px; font-weight: bold; margin: 30px 0; }
</style></head>
<body>
    <h1>Sertifikat Penghargaan</h1>
    <p>Diberikan kepada</p>
    <div class="name">{{ $studentName }}</div>
    <p>atas pencapaian: <strong>{{ $awardName }}</strong></p>
    <p>{{ $issuedAt }}</p>
</body>
</html>