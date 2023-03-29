<!doctype html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport"
          content="width=device-width, user-scalable=no, initial-scale=1.0, maximum-scale=1.0, minimum-scale=1.0">
    <meta http-equiv="X-UA-Compatible" content="ie=edge">
    <title>开始进行初始化</title>
</head>
<body>
<form action="{{route('onedrive.app.init')}}">
    <div>
        初始化Onedrive:
        <input type="checkbox" name="init_onedrive" value="1" checked>
    </div>
    <div>
        <div style="color: red">删除所有onedrive_作为开头的用户(只有在初始化Onedrive时才会生效)危险操作后果自负):</div>
        <input type="checkbox" name="delete_user" value="1">
    </div>
    <div>
        取消所有用户的E5许可(只有在初始化Onedrive时才会生效)):
        <input type="checkbox" name="cancel_permission" value="1">
    </div>
    <div>
        E5保活:
        <input type="checkbox" name="save_e5" value="1" checked>
    </div>
    <button>开始</button>
</form>
</body>
</html>
