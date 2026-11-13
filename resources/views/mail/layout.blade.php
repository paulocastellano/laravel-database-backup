<!DOCTYPE html>
<html lang="en" dir="ltr">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <meta name="x-apple-disable-message-reformatting">
  <meta name="color-scheme" content="light only">
  <title>@yield('title')</title>
</head>
<body style="margin: 0; padding: 0; width: 100%; background-color: #f7f6f3; -webkit-font-smoothing: antialiased;">
  <div style="display: none; max-height: 0; overflow: hidden;">@yield('preview')</div>
  <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="width: 100%; background-color: #f7f6f3; font-family: Inter, Arial, Helvetica, sans-serif;">
    <tr>
      <td align="center" style="padding: 32px 16px;">
        <table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="width: 100%; max-width: 552px;">
          <tr>
            <td style="padding-bottom: 24px; font-size: 16px; font-weight: 600; color: #292928;">{{ $appName }}</td>
          </tr>
          <tr>
            <td style="border: 1px solid #eae8e5; border-radius: 12px; background-color: #ffffff; padding: 32px; font-size: 16px; line-height: 24px; color: #292928;">
              @yield('content')
            </td>
          </tr>
          <tr>
            <td align="center" style="padding: 24px; font-size: 12px; line-height: 20px; color: #5a5a59;">
              Sent by the database backup of {{ $appName }} ({{ $environment }}).
            </td>
          </tr>
        </table>
      </td>
    </tr>
  </table>
</body>
</html>
