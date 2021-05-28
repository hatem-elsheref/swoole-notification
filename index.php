<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <title>ws</title>
</head>
<body>



<h1>
    SEND NOTIFICATION NOW
</h1>



<input type="hidden" id="user_id" value="<?=$_GET['id']?>">
<input type="text" id="message" value="">
<button id="send">Send</button>


<script>

    let socket = new WebSocket("ws://localhost:9000")
    socket.onopen = function (){
        socket.send(JSON.stringify({user_id: document.getElementById('user_id').value, operation: 'open'}))
        console.log("connection opened successfully")
    }
    socket.onmessage = function (message){
        alert(message.data)
    }

    socket.onclose = function (){
        socket.send(JSON.stringify({user_id: document.getElementById('user_id').value, operation: 'close'}))
        console.log("connection closed successfully")
    }
    document.getElementById("send").addEventListener("click", function (e){
        let message = document.getElementById("message").value;
        sendNotification(message, socket);
    })

    function sendNotification(message, socket){
        let data = {message: message, time : Date.now()}
        socket.send(JSON.stringify(data))
    }


</script>
</body>
</html>
