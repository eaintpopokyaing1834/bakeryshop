<?php
$file = 'includes/lang/my.php';
$content = file_get_contents($file);

// Remove the closing bracket safely
$content = preg_replace('/\];\s*$/', '', $content);

// Append the new translation keys with proper UTF-8 Myanmar text
$content .= "\n    'review_only_purchased'     => 'သင်ဝယ်ယူပြီး လက်ခံရရှိထားသော ထုတ်ကုန်များကိုသာ သုံးသပ်နိုင်ပါသည်။',\n";
$content .= "    'detail_please_select_rating' => 'ကျေးဇူးပြု၍ အဆင့်သတ်မှတ်ချက်တစ်ခုကို ရွေးချယ်ပါ။',\n";
$content .= "    'review_login_required'     => 'သုံးသပ်ချက်ရေးရန် ကျေးဇူးပြု၍ အကောင့်ဝင်ပါ။',\n";
$content .= "    'review_invalid_rating'     => 'အဆင့်သတ်မှတ်ချက် မှားယွင်းနေပါသည်။',\n";
$content .= "    'review_updated'            => 'သုံးသပ်ချက်ကို ပြင်ဆင်ပြီးပါပြီ!',\n";
$content .= "    'review_submitted'          => 'သုံးသပ်ချက်ကို ပေးပို့ပြီးပါပြီ!',\n";
$content .= "];\n";

file_put_contents($file, $content);
echo "Done";
