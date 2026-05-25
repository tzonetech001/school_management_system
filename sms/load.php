<!DOCTYPE html>
<html>
<head>
<style>

body{
margin:0;
height:100vh;
display:flex;
justify-content:center;
align-items:center;
background:#f5f5f5;
}

.loader{
display:flex;
gap:6px;
align-items:flex-end;
}

.loader div{
width:10px;
background:#007bff;
animation:load 1s infinite ease-in-out;
}

.loader div:nth-child(1){height:10px; animation-delay:0s;}
.loader div:nth-child(2){height:20px; animation-delay:0.1s;}
.loader div:nth-child(3){height:30px; animation-delay:0.2s;}
.loader div:nth-child(4){height:40px; animation-delay:0.3s;}
.loader div:nth-child(5){height:30px; animation-delay:0.4s;}
.loader div:nth-child(6){height:20px; animation-delay:0.5s;}

@keyframes load{
0%,100%{transform:scaleY(1);}
50%{transform:scaleY(2);}
}

</style>
</head>
<body>

<div class="loader">
<div></div>
<div></div>
<div></div>
<div></div>
<div></div>
<div></div>
</div>

</body>
</html>