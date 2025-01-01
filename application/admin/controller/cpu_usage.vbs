On Error Resume Next
    Set objProc = GetObject("winmgmts:\\.\root\cimv2:win32_processor='cpu0'")
    WScript.Echo(objProc.LoadPercentage)