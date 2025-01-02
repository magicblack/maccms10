On Error Resume Next
    Set objWMI = GetObject("winmgmts:\\.\root\cimv2")
    Set colOS = objWMI.InstancesOf("Win32_OperatingSystem")
    For Each objOS in colOS
     Wscript.Echo("{""TotalVisibleMemorySize"":" & objOS.TotalVisibleMemorySize & ",""FreePhysicalMemory"":" & objOS.FreePhysicalMemory & "}")
    Next