<?php
/* NAME
 *
 *  amclient.php
 *
 * CONCEPT
 *
 *  Test the service that adds user suggestion to a session record.
 */
 
 preg_match('/^(.+)\/[^\/]+$/', $_SERVER['SCRIPT_NAME'], $match);
 $spath = $match[1];
?>
<!DOCTYPE html>
<html>

  <head>
    
    <title>Suggestion Client</title>

    <style>
      body {
        font-family: sans-serif;
      }
      #f {
        display: grid;
        grid-template-columns: repeat(2, auto);
        width: max-content;
        grid-column-gap: 1vw;
        grid-row-gap: 1vh;
        margin: 2vw;
        padding: .5vw;
        background-color: #ccc;
        border: 1px solid black;
      }
      .fh {
        font-weight: bold;
        text-align: right;
      }
      #sub {
        grid-column: span 2;
        text-align: center;
        font-weight: bold;
      }
    </style>

    <script>

      async function bird() {
          url = service + session_id.value
          suggestion = suggestion_el.value
          rando = key_el.value
          payload = JSON.stringify({
  suggestion: suggestion,
  rando: rando
      })
          request = new Request(url, {
	      method: "POST",
	      body: payload
	  })
	  response = await fetch(request)
      } // end bird()

  </script>

  </head>

  <body>

    <h1>Suggestion Client</h1>

    <p>Send a POST request to <code>suggestion.php</code> with a JSON payload
      based on user input from the form below.</p>

    <div id="f">
      <div class="fh">Suggestion:</div>
      <div>
	<textarea name="suggestion" id="suggestion" rows="3" cols="80"></textarea>
      </div>
      <div class="fh">Session ID:</div>
      <div>
	<input type="text" name="session_id" id="session_id">
      </div>
      <div class="fh">Key:</div>
      <div>
	<input type="text" name="key" id="key">
      </div>
      <div id="sub">
	<button id="butt">Send request</button>
      </div>
    </div>
      
    <script>
      const server = '<?=$_SERVER['SERVER_NAME']?>'
      const spath = '<?=$spath?>'
      let service = 'https://' + server + spath + '/suggestion.php/session/' 
      const session_id = document.querySelector('#session_id')
      const butt = document.querySelector('#butt')
      const suggestion_el = document.querySelector('#suggestion')
      const key_el = document.querySelector('#key')
      butt.addEventListener('click', bird)
    </script>

  </body>
</html>
