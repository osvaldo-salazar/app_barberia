<!doctype html>
<html lang="es">
<body style="margin:0;padding:24px;background:#f2f4f8;font-family:Arial,Helvetica,sans-serif;color:#1b2438">
  <table role="presentation" width="100%" cellpadding="0" cellspacing="0"><tr><td align="center">
    <table role="presentation" width="480" cellpadding="0" cellspacing="0" style="max-width:480px;background:#ffffff;border-radius:12px;overflow:hidden">
      <tr><td style="background:#121b31;color:#ffffff;padding:18px 24px;font-size:18px;font-weight:bold">{{ config('app.name') }}</td></tr>
      <tr><td style="padding:24px">
        <p style="margin:0 0 12px">Hola {{ $name }},</p>
        <p style="margin:0 0 20px">Recibimos una solicitud para cambiar la clave de tu cuenta. Toca el botón para elegir una nueva:</p>
        <p style="text-align:center;margin:0 0 20px">
          <a href="{{ $url }}" style="display:inline-block;background:#3568c9;color:#ffffff;text-decoration:none;padding:13px 26px;border-radius:8px;font-weight:bold">Cambiar mi clave</a>
        </p>
        <p style="margin:0 0 12px">El enlace vence en <strong>{{ $minutes }} minutos</strong> y solo se puede usar una vez. Si no fuiste tú, ignora este correo: tu clave no cambia.</p>
        <p style="margin:0;color:#5a6479;font-size:12px;word-break:break-all">Si el botón no funciona, copia este enlace en tu navegador:<br>{{ $url }}</p>
      </td></tr>
    </table>
  </td></tr></table>
</body>
</html>