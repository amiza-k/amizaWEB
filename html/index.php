<?php
session_start();
require_once 'mysql.php';

// Handle tweet submission
$tweet_message = '';
$tweet_error = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['tweet_content'])) {
    if (!isset($_SESSION['login']) || !isset($_SESSION['user_id'])) {
        $tweet_error = "You must be logged in to post a tweet.";
        exit();
    } else {
        $tweet_content = trim($_POST['tweet_content']);
        $user_id = $_SESSION['user_id'];

        if (empty($tweet_content)) {
            $tweet_error = "Tweet content cannot be empty.";
        } elseif (strlen($tweet_content) > 280) {
            $tweet_error = "Tweet content cannot exceed 280 characters.";
        } else {
            $stmt = $connection->prepare("INSERT INTO tweets (user_id, content, created_at) VALUES (?, ?, NOW())");
            if ($stmt) {
                $stmt->bind_param("is", $user_id, $tweet_content);
                if ($stmt->execute()) {
                    $tweet_message = "Tweet posted successfully!";
                    header("Location: /index.php?tweet_success=1");
                    exit();
                } else {
                    $tweet_error = "Failed to post tweet. Please try again.";
                }
                $stmt->close();
            } else {
                $tweet_error = "Database error. Please try again.";
            }
        }
    }
}

// Check for success message from redirect
if (isset($_GET['tweet_success']) && $_GET['tweet_success'] == 1) {
    $tweet_message = "Tweet posted successfully!";
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>amizaWEBAPP</title>
  <link rel="stylesheet" href="//static.amizaWEB/style.css">
</head>
<body class="index-page">
  <div class="container">
    <h1>welcome to the home page</h1>
    <p class="lead">Welcome to amizaWEBAPP</p>
    
    <?php if (isset($_SESSION['login']) && $_SESSION['login'] === true) { ?>
      <div class="tweet-form-section">
        <h3>Post a Tweet</h3>
        <?php if (!empty($tweet_message)) { ?>
          <div class="alert success"><?php echo htmlspecialchars($tweet_message, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php } ?>
        <?php if (!empty($tweet_error)) { ?>
          <div class="alert error"><?php echo htmlspecialchars($tweet_error, ENT_QUOTES, 'UTF-8'); ?></div>
        <?php } ?>
        <form method="POST" action="/index.php" class="tweet-form" autocomplete="off">
          <div class="field">
            <textarea 
              id="tweet_content" 
              name="tweet_content" 
              rows="4" 
              maxlength="280" 
              placeholder="What's happening?"
              required><?php echo isset($_POST['tweet_content']) ? htmlspecialchars($_POST['tweet_content'], ENT_QUOTES, 'UTF-8') : ''; ?></textarea>
            <div class="char-count">
              <span id="char-counter">0</span>/280
            </div>
          </div>
          <button id="t_s_button" type="button" name="tweet_submit">submit Tweet</button>
        </form>
      </div>
    <?php } ?>
    <div class="nav-list nav-right">
      <a href="login.php">Login</a>
      <a href="register.php">Register</a>
      <a href="panel.php">Panel</a>
      <a href="all_users.php">All Users</a>
    </div>

    <div class="tweet-feed-container">
      <div class="tweets-feed-title">Latest Tweets</div>
      <div id="tweets-feed">
        <div class="empty-feed">Loading tweets...</div>
      </div>
    </div>
  </div>
  <script>
    // Character counter for tweet textarea
    const tweetTextarea = document.getElementById('tweet_content');
    const charCounter = document.getElementById('char-counter');
    
    if (tweetTextarea && charCounter) {
        tweetTextarea.addEventListener('input', function() {
            const length = this.value.length;
            charCounter.textContent = length;
            
            if (length > 280) {
                charCounter.style.color = '#dc2626';
            } else if (length > 260) {
                charCounter.style.color = '#f59e0b';
            } else {
                charCounter.style.color = '#6c757d';
            }
        });
        charCounter.textContent = tweetTextarea.value.length;
    }
  </script>
</body>
<script>

  document.getElementById('t_s_button').addEventListener('click', function() {
    const t_s_button = document.getElementById('t_s_button');
    t_s_button.disabled = true;
    t_s_button.innerHTML = 'submit...';
    const tweetContent = document.getElementById('tweet_content').value;
    const xhr = new XMLHttpRequest();
    xhr.open('POST', 'tweets.php', true);
    xhr.setRequestHeader('Content-Type', 'application/json');
    xhr.send(JSON.stringify({ tweet_content: tweetContent }));
    xhr.onreadystatechange = function() {
      if (xhr.readyState === 4) {
        try{
        data = JSON.parse(xhr.responseText);
      }catch (error){
        alert('failed to parse response');
        return;
      }
        if (data.status){
          alert(data.status);
        }else{
          alert(data.error);
        }
        setTimeout(function() {
          document.getElementById('t_s_button').disabled = false;
          var btn = document.getElementById('t_s_button');
          if (btn && btn.parentNode) {
            btn.parentNode.removeChild(btn);
          }
          location.reload(1);
        }, 2000);
      }
    }
  });

    // Function to escape HTML to prevent XSS
    function escapeHtml(text) {
        if (text == null) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }

    // Function to format date
    function formatDate(dateString) {
        const date = new Date(dateString);
        if (isNaN(date)) return 'Invalid date';
        const months = ['Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec'];
        const month = months[date.getMonth()];
        const day = date.getDate();
        const year = date.getFullYear();
        const hours = date.getHours();
        const minutes = date.getMinutes();
        const ampm = hours >= 12 ? 'PM' : 'AM';
        const displayHours = hours % 12 || 12;
        const displayMinutes = minutes < 10 ? '0' + minutes : minutes;
        return `${month} ${day}, ${year} ${displayHours}:${displayMinutes} ${ampm}`;
    }

    // Load and render tweets
    const tweetsXhr = new XMLHttpRequest();
    tweetsXhr.open('GET', 'tweets.php', true);
    tweetsXhr.onreadystatechange = function() {
        if (tweetsXhr.readyState === 4) {
            const tweetsFeed = document.getElementById('tweets-feed');
            if (!tweetsFeed) return;
            
            if (tweetsXhr.status === 200) {
                try {
                    const responseText = tweetsXhr.responseText.trim();
                    if (!responseText) {
                        tweetsFeed.innerHTML = '<div class="empty-feed">No tweets found.</div>';
                        return;
                    }
                    
                    const tweets = JSON.parse(responseText);
                    if (Array.isArray(tweets) && tweets.length > 0) {
                        let html = '';
                        tweets.forEach(function(tweet) {
                            const profilePicture = tweet.profile_picture 
                                ? 'http://static.amizaWEB/user_profiles/' + escapeHtml(tweet.profile_picture)
                                : 'http://static.amizaWEB/user_profiles/default.png';
                            const name = escapeHtml(tweet.name || 'Unknown User');
                            const username = escapeHtml(tweet.username || 'unknown');
                            const content = escapeHtml(tweet.content || '');
                            const time = formatDate(tweet.created_at);
                            const userId = tweet.user_id || tweet.id || 0;

                            html += '<div class="tweet-card">';
                            html += '  <div class="tweet-header">';
                            html += '    <a href="profile.php?user_id=' + userId + '">';
                            html += '      <img src="' + profilePicture + '" alt="pfp" class="tweet-pfp" loading="lazy" />';
                            html += '    </a>';
                            html += '    <span class="tweet-author">';
                            html += '      <a href="profile.php?user_id=' + userId + '" style="color:inherit;text-decoration:none;">';
                            html += name;
                            html += '      </a>';
                            html += '    </span>';
                            html += '    <span class="tweet-username">@' + username + '</span>';
                            html += '    <span class="tweet-time">' + time + '</span>';
                            html += '  </div>';
                            html += '  <div class="tweet-content">' + content.replace(/\n/g, '<br>') + '</div>';
                            html += '</div>';
                        });
                        tweetsFeed.innerHTML = html;
                    } else {
                        tweetsFeed.innerHTML = '<div class="empty-feed">No tweets found.</div>';
                    }
                } catch (e) {
                    console.error('Error parsing tweets:', e);
                    console.error('Response:', tweetsXhr.responseText);
                    tweetsFeed.innerHTML = '<div class="empty-feed">Error loading tweets. Please refresh the page.</div>';
                }
            } else {
                console.error('HTTP Error:', tweetsXhr.status, tweetsXhr.statusText);
                tweetsFeed.innerHTML = '<div class="empty-feed">Error loading tweets (Status: ' + tweetsXhr.status + '). Please refresh the page.</div>';
            }
        }
    };
    tweetsXhr.send();
</script>
</html>
