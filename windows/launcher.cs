// SPDX-License-Identifier: PolyForm-Shield-1.0.0
// Client Work Hub.exe: what the Start-menu and desktop shortcuts open. The installer builds it on the PC with the
// C# compiler that comes with Windows (.NET Framework 4), so nothing is downloaded and nothing needs signing.
// It runs app\windows\start.ps1 with no console window at all (a hidden PowerShell window still flashes) and
// exits; start.ps1 does the work and stops the servers when the application window closes.
// Written for the old C# 5 compiler.
using System;
using System.Diagnostics;
using System.IO;
using System.Reflection;
using System.Windows.Forms;

[assembly: AssemblyTitle("Client Work Hub")]
[assembly: AssemblyProduct("Client Work Hub")]
[assembly: AssemblyDescription("Client Work Hub")]

static class CwhLauncher
{
    [STAThread]
    static int Main()
    {
        string dir = Path.GetDirectoryName(Assembly.GetExecutingAssembly().Location);
        string script = Path.Combine(dir, "app\\windows\\start.ps1");
        if (!File.Exists(script))
        {
            MessageBox.Show("A part of Client Work Hub is missing:\n" + script + "\n\nInstall it again.",
                            "Client Work Hub", MessageBoxButtons.OK, MessageBoxIcon.Error);
            return 1;
        }
        string ps = Path.Combine(Environment.GetFolderPath(Environment.SpecialFolder.System),
                                 "WindowsPowerShell\\v1.0\\powershell.exe");
        ProcessStartInfo psi = new ProcessStartInfo(ps,
            "-NoProfile -ExecutionPolicy Bypass -WindowStyle Hidden -File \"" + script + "\"");
        psi.UseShellExecute = false;
        psi.CreateNoWindow = true;
        psi.WorkingDirectory = dir;
        try { Process.Start(psi); }
        catch (Exception e)
        {
            MessageBox.Show("Client Work Hub could not start PowerShell:\n" + e.Message,
                            "Client Work Hub", MessageBoxButtons.OK, MessageBoxIcon.Error);
            return 1;
        }
        return 0;
    }
}
