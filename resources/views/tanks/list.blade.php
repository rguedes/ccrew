<html>
<head>
    <style type="text/css">
        ul {
            list-style: none;
        }
        li {
            display: inline-block;
            width: 250px;
            padding: 10px;
            margin-bottom: 20px;
            margin-right: 10px;
        }
        li.success {
            background: green;
        }
        li.warning {
            background: orange;
        }
        li.danger {
            background: red;
            color: #ffffff;
        }
        li.unicorn {
            background: purple;
            color: #ffffff;
        }
        .btn {
            background: #f2dede;
            padding: 10px;
            text-decoration: none;
            color: #000;
        }
    </style>
</head>
<body>
<a href="{{url("wot")}}">Back to clan list</a><h3>{{$user['nickname']}}</h3>

@for($i = 1; $i < 11; $i++)
    <a class="btn" href="{{ url("/wot/".$user['account_id'])."/".$i }}">{{$i}}</a>
@endfor
<ul>
@foreach($tanks as $tank)
    <li class="{{$tank['class']}}">
    @include('tanks.view', ['tank' => $tank['tank']])
    @include('account.stats', ['tank' => $tank])
    </li>
@endforeach
</ul>

</body>
</html>

