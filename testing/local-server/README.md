# Local Test Server

`Start-LocalTestServer.ps1` generates `index.html` from the HTML files in this directory and starts a local Python static server.

```powershell
.\Start-LocalTestServer.ps1
```

Place standalone test pages beside the launcher. The generated index excludes itself and links to each remaining `.html` file.

For phone testing on the same Wi-Fi network, bind to all interfaces and use the computer's LAN IP on the phone:

```powershell
.\Start-LocalTestServer.ps1 -HostName 0.0.0.0
```

Stop the server by ending the returned `ProcessId`:

```powershell
Stop-Process -Id <ProcessId>
```
