<h1><?php echo $this->escape($this->title); ?></h1> 
 
<?php if (!empty($this->message)) { ?> 
<div id="message"> 
<?php echo $this->escape($this->message); ?> 
</div> 
<?php } ?> 
 
<form action="<?php echo $this->baseUrl ?>/auth/login" method="post"> 
<div> 
    <label for="username">Username</label> 
    <input type="text" name="username" value=""/> 
</div> 
<div> 
    <label for="password">Password</label> 
    <input type="password" name="password" value=""/> 
</div> 
<div id="formbutton"> 
<input type="submit" name="login" value="Login" /> 
</div> 
</form>  
