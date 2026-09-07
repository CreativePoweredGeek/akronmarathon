<?php
use BoldMinded\DataGrab\Model\ImportStatus;

$isComplete = $status == ImportStatus::COMPLETED;

$e = static fn($value): string => htmlspecialchars((string) $value, ENT_QUOTES, 'UTF-8');
?>

<html>
<head>
    <?php if ($refreshTimeout !== null && $refreshUrl): ?>
        <script>
            var timeout = <?php echo (int) $refreshTimeout ?>;
            var timeleft = timeout;
            var countdownTimer = setInterval(function(){
                if(timeleft <= 0){
                    clearInterval(countdownTimer);
                }
                document.getElementById("refreshIn").innerHTML = timeleft;
                timeleft -= 1;
            }, 1000);
        </script>
        <meta http-equiv="refresh" content="<?php echo (int) $refreshTimeout ?>;url=<?php echo $e($refreshUrl) ?>">
    <?php endif; ?>
    <?php echo $styleTag ?>
    <style>
        .panel {
            min-width: 500px;
            position: absolute;
            left: 50%;
            top: 50%;
            transform: translate(-50%,-50%);
        }
        #log {
            display: none;
        }
        #log.visible {
            display: block;
        }
        .continue {
            margin-top: 2em;
        }
        .desc {
            color: var(--ee-text-secondary);
            font-size: 0.8em;
        }
    </style>
</head>
<body>
<div class="panel">
    <div class="panel-heading">
        <h3><?php echo $e($importName) ?></h3>
    </div>
    <div class="panel-body">
        <?php echo $display_status ?>
        <?php if (!$isComplete): ?>
            <p class="continue" title="This is the default consumer timeout.">Continuing in... <span id="refreshIn"></span></p>
        <?php endif; ?>
    </div>
    <?php if ($logFile): ?>
    <div class="panel-body">
        <textarea id="log" rows="12"><?php echo $e($logFile) ?></textarea>
    </div>
    <?php endif; ?>
    <div class="panel-footer">
        <div class="form-btns">
            <?php if ($logFile): ?>
            <a href="#" id="showLog" class="button button--default" style="float: left">Show Log</a>
            <script>
                var showLog = document.getElementById('showLog');
                showLog.addEventListener('mouseup', function () {
                    document.getElementById('log').classList.add('visible');
                });
            </script>
            <?php endif; ?>
            <?php if (!$isComplete): ?>
                <a class="button button--primary" href="<?php echo $e($refreshUrl) ?>" title="This will immediately start another consumer to import another batch of entries from the queue">Continue Now</a>
            <?php endif; ?>
            <div class="clear-float"></div>
        </div>
    </div>
</div>

</body>
</html>
