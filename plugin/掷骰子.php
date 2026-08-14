<?php
if (!in_array(消息来源, ["群聊", "私聊"])) {
    return;
}

if (消息 == "骰子" || 消息 == "掷骰子" || 消息 == "摇骰子") {
    $num = rand(1, 6);
    $dice = ["⚀", "⚁", "⚂", "⚃", "⚄", "⚅"];
    
    文字($dice[$num-1] . " 你掷出了：" . $num . "点");
    return;
}

if (前缀(消息, "骰子 ")) {
    $count = intval(前缀后(消息, "骰子 "));
    if ($count < 1 || $count > 10) {
        文字("❌ 骰子数量只能是1-10个");
        return;
    }
    
    $result = [];
    $total = 0;
    $dice = ["⚀", "⚁", "⚂", "⚃", "⚄", "⚅"];
    
    for ($i=0; $i<$count; $i++) {
        $num = rand(1, 6);
        $result[] = $dice[$num-1];
        $total += $num;
    }
    
    文字(implode(" ", $result) . "\n\n总计：" . $total . "点");
    return;
}
