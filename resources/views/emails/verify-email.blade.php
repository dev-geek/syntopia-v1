<!DOCTYPE html>
<html>
<head>
    <title>Verify Your Email</title>
</head>
<body>
    <h2>Welcome to Syntopia!</h2>
    <p>Please click the button below to verify your email address:</p>
    
    <a href="{{ $verificationUrl }}" 
       style="background-color: rgb(62, 87, 218); 
              color: white; 
              padding: 10px 20px; 
              text-decoration: none; 
              border-radius: 5px; 
              display: inline-block;">
        Verify Email Address
    </a>

    <p>If you did not create an account, no further action is required.</p>
</body>
</html> 