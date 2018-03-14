<?php

return [

    'messagePatterns' => [
        [
            'type' => 'partial',
            'matches' => [
                'insufficient balance',
            ],
            'modifiedMessage' => 'Insufficient balance.',
        ],
        [
            'type' => 'exact',
            'matches' => [
                'Transaction failed due to invalid mobile number',
            ],
            'modifiedMessage' => 'Invalid mobile number.',
        ],
        [
            'type' => 'exact',
            'matches' => [
                '0: Your request cannot be processed at this time, please try again later.',
            ],
            'modifiedMessage' => 'Your request cannot be processed at this time, please try again later.',
        ],
        [
            'type' => 'exact',
            'matches' => [
                '0: Your request cannot be processed at this time, please try later. Thank you.',
            ],
            'modifiedMessage' => 'Your request cannot be processed at this time, please try again later.',
        ],
        [
            'type' => 'exact',
            'matches' => [
                '1: Order {2} Tk to {0} is successful. Transaction number is {1}.Your new balance is {3} TAKA. Thank You.',
            ],
            'modifiedMessage' => 'Failed at operator end and refunded.',
        ],
        [
            'type' => 'exact',
            'matches' => [
                '1100122: Order attempted from restricted number',
            ],
            'modifiedMessage' => 'Order attempted from restricted number.',
        ],
        [
            'type' => 'exact',
            'matches' => [
                '0: Your request cannot be processed at this time, please try again later.',
                '17007: Your request cannot be processed at this time, please try again later.',
                '23070: Your request cannot be processed at this time, please try again later.',
                '250: Your request cannot be processed at this time, please try again later.',
            ],
            'modifiedMessage' => 'Your request cannot be processed at this time, please try again later.',
        ],
        [
            'type' => 'regex',
            'matches' => [
                '/^1030003:\s*Your request to order \d+ is already in queue\./',
                '/^2020:\s*The previous request of the recipient \d+ is under process/',
            ],
            'modifiedMessage' => 'Your another request is in process, please try again later.',
        ],
        [
            'type' => 'regex',
            'matches' => [
                '/^3006201: The mobile number \d+ cannot use the same order service within ([\d\.]+) minutes/',
            ],
            'modifiedMessage' => 'Please try again after 2 mins.',
        ],

        [
            'type' => 'regex',
            'matches' => [
                '/^6010: Your current request to transfer \d+ TAKA cannot be processed as you do not have enough credit/',
            ],
            'modifiedMessage' => 'Internal error encountered. Please contact administrator.',
        ],
        [
            'type' => 'regex',
            'matches' => [
                '/^7018:\s*The mobile number \d+\s*is incorrect\. It should be consisting of \d+ digits\./u',
            ],
            'modifiedMessage' => 'Incorrect mobile number.',
        ],
        [
            'type' => 'regex',
            'matches' => [
                '/^2051: Your current request to transfer \d+ TAKA cannot be processed as the daily number of transfer for \d+ is \d+ and the maximum allowed number of transfer is \d+\./',
            ],
            'modifiedMessage' => 'This number reached daily allowed order limit.',
        ],
        [
            'type' => 'exact',
            'matches' => [
                '7048: Receiver mobile number is from un-supported network.',
            ],
            'modifiedMessage' => 'Receiver mobile number is from un-supported network.',
        ],
        [
            'type' => 'exact',
            'matches' => [
                '8514: System upgradation activity is ongoing. Please try later.',
            ],
            'modifiedMessage' => 'System upgradation activity is ongoing. Please try later.',
        ],
        [
            'type' => 'exact',
            'matches' => [
                '9007: An error occurred while processing your request. We will be solving it shortly. Please try again later.',
            ],
            'modifiedMessage' => 'An error occurred while processing your request. We will be solving it shortly. Please try again later.',
        ],
        [
            'type' => 'regex',
            'matches' => [
                '/Failed connect to\s*(?<ip>[\d\.]+):\d+;\s*No route to host/',
            ],
            'modifiedMessage' => 'Processing error.',
        ],
        [
            'type' => 'regex',
            'matches' => [
                '/Failed connect to\s*(?<ip>[\d\.]+):\d+;\s*/',
            ],
            'modifiedMessage' => 'Processing error.',
        ],
        [
            'type' => 'regex',
            'matches' => [
                '/^2071:\s*Invalid transfer value/',
            ],
            'modifiedMessage' => 'Invalid transfer value.',
        ],
        [
            'type' => 'exact',
            'matches' => [
                '1100122: Order attempted from restricted number',
            ],
            'modifiedMessage' => 'Order attempted from restricted number.',
        ],
        [
            'type' => 'regex',
            'matches' => [
                '/transaction failed due to (invalid|incorrect) mobile number/i',
            ],
            'modifiedMessage' => 'Invalid mobile number',
        ],
        [
            'type' => 'regex',
            'matches' => [
                '/The mobile number\s*(?<number>\d+)\s*cannot use the same order service within\s*(?<minutes>[\d\.]+)\s*minutes./',
            ],
            'modifiedMessage' => 'Mobile Number: {number}; Duplicate transaction within {minutes} minutes.',
        ],
        [
            'type' => 'regex',
            'matches' => [
                '/(?<id>\d+):\s*Invalid transfer value\s*(?<value>\d+)/',
            ],
            'modifiedMessage' => 'Invalid transfer value: {value}.',
        ],
        [
            'type' => 'regex',
            'matches' => [
                '/Time limit error,\syou can\'t order same number and same amount within\s*(?<minutes>\d+)\s*minutes/',
            ],
            'modifiedMessage' => 'Time limit error; Duplicate order attempt within {minutes} minutes.',
        ],
        [
            'type' => 'regex',
            'matches' => [
                '/(?<id>\d+):\s*Your request to order\s*(?<number>\d+)\s*is already in queue/',
            ],
            'modifiedMessage' => 'Order request for {number} is already in queue.',
        ],
        [
            'type' => 'regex',
            'matches' => [
                '/(?<id>\d+):\s*The previous request of the recipient\s*(?<number>\d+)\s*is under process/',
            ],
            'modifiedMessage' => 'Previous request for {number} is still being processed.',
        ],
        [
            'type' => 'regex',
            'matches' => [
                '/Operation timed out after\s*(?<milliseconds>\d+)\s*milliseconds/',
            ],
            'modifiedMessage' => 'Processing error.',
        ],
        [
            'type' => 'regex',
            'matches' => [
                '/^2003:.*?(দুঃখিত, আপনি সাময়িকভাবে এই পরিষেবা ব্যবহার থেকে বাধা দান করা|গ্রাহক সেবা যোগাযোগ করুন).*$/u',
            ],
            'modifiedMessage' => 'Sorry, this number is restricted. Please contact customer support.',
        ],
        [
            'type' => 'regex',
            'matches' => [
                '/^(206:\s*)?17007:.*?(আপনার অনুরোধ এই সময়ে প্রক্রিয়াভুক্ত করা যাবে না|পরে আবার চেষ্টা করুন).*$/u',
            ],
            'modifiedMessage' => 'Your request cannot be processed at this time, please try again later.',
        ],
        [
            'type' => 'regex',
            'matches' => [
                '/^(206:\s*)?17017:.*?(আপনার অনুরোধ এই সময়ে প্রক্রিয়াভুক্ত করা যাবে না|পরে আবার চেষ্টা করুন).*$/u',
            ],
            'modifiedMessage' => 'Your request cannot be processed at this time, please try again later.',
        ],
        [
            'type' => 'regex',
            'matches' => [
                '/^(206:\s*)?251:.*?(আপনার অনুরোধ এই সময়ে প্রক্রিয়াভুক্ত করা যাবে না|পরে আবার চেষ্টা করুন).*$/u',
            ],
            'modifiedMessage' => 'Your request cannot be processed at this time, please try again later.',
        ],
        [
            'type' => 'regex',
            'matches' => [
                '/^(206:\s*)?3006202:.*?(মোবাইল নম্বর).*?(সর্বশেষ স্থানান্তর পরিমাণ).*?(গত সফল লেনদেনের মিনিট বর্তমান).*?(অনুরোধ পরিমাণ হিসাবে একই মধ্যে কোন).*?(রিচার্জ সার্ভিস ব্যবহার করতে পারবেন না)/u',
            ],
            'modifiedMessage' => 'Duplicate transaction. Please try again.',
        ],
        [
            'type' => 'regex',
            'matches' => [
                '/^(206:\s*)?4354:.*?এই মোবাইল নম্বর সিরিজ পদ্ধতি হিসেবে সংজ্ঞায়িত করা হয় না$/',
            ],
            'modifiedMessage' => 'This number is not valid',
        ],
        [
            'type' => 'regex',
            'matches' => [
                '/^(206:\s*)?6010:.*?(আপনি যথেষ্ট ক্রেডিট নেই|আপনার বর্তমান অনুরোধ|প্রক্রিয়াভুক্ত করা যাবে না).*$/u',
            ],
            'modifiedMessage' => 'Internal error encountered. Please contact administrator.',
        ],
        [
            'type' => 'regex',
            'matches' => [
                '/^(206:\s*)?7017:.*?মোবাইল নম্বর.*? \d+ ডিজিটের হওয়া উচিত/u',
            ],
            'modifiedMessage' => 'Incorrect mobile number.',
        ],
        [
            'type' => 'regex',
            'matches' => [
                '/^(206:\s*)?7048:.*?রিসিভার মোবাইল নম্বর.*? জাতিসংঘ-সমর্থিত নেটওয়ার্ক থেকে হয়/u',
            ],
            'modifiedMessage' => 'Receiver mobile number is from un-supported network.',
        ],
        [
            'type' => 'regex',
            'matches' => [
                '/^(206:\s*)?8500:.*?অবৈধ নির্বাচক মূল্য.*?গ্রাহক সেবা যোগাযোগ করুন/u',
            ],
            'modifiedMessage' => 'Invalid transfer value.',
        ],
        [
            'type' => 'regex',
            'matches' => [
                '/^(206:\s*)?error\.general\.sql\.processing:.*?আপনার অনুরোধ এই সময়ে প্রক্রিয়াভুক্ত করা যাবে না/u',
            ],
            'modifiedMessage' => 'Your request cannot be processed at this time, please try again later.',
        ],
        [
            'type' => 'regex',
            'matches' => [
                '/^(206:\s*)?7000:.*?আপনার আগের অনুরোধ প্রক্রিয়াধীন রয়েছে, পরে আবার চেষ্টা করুন/u',
            ],
            'modifiedMessage' => 'Your another request is in process, please try again later.',
        ],
        [
            'type' => 'regex',
            'matches' => [
                '/^(206:\s*)?8514:.*?নেটওয়ার্ক সাময়িকভাবে স্থগিত করা হয়, পরে আবার চেষ্টা করুন/u',
            ],
            'modifiedMessage' => 'Processing error.',
        ],
        [
            'type' => 'regex',
            'matches' => [
                '/^(206:\s*)?205:.*?লেনদেন সংখ্যা.*?সফল করার.*?আপনার নতুন ভারসাম্য/u',
            ],
            'modifiedMessage' => 'Transaction failed.',
        ],
        [
            'type' => 'regex',
            'matches' => [
                '/Failed connect to \d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3}:\d+; Network is unreachable/',
            ],
            'modifiedMessage' => 'Processing error.',
        ],
        [
            'type' => 'regex',
            'matches' => [
                '/^(206:\s*)?1020:\s*Sorry, your requested order amount cannot be processed\.\s*To avail your desired internet pack please dial \*121\*3# or click - https:\/\/mygp\.li\/B3/u',
            ],
            'modifiedMessage' => 'Sorry, the amount attempted was restricted by the operator. Please try with a different amount.',
        ],
        [
            'type' => 'regex',
            'matches' => [
                '/^206:\s*115:\s*Duplicate transaction detected\.$/',
            ],
            'modifiedMessage' => 'Duplicate Transaction. Please try again after 5 mins.',
        ],
        [
            'type' => 'regex',
            'matches' => [
                '/^206:\s*126:\s*You provided an amount outside the margins of the contract\. Please try again with a valid amount\.$/',
            ],
            'modifiedMessage' => 'The provided amount is not accepted. Please try again with a valid amount.',
        ],
        [
            'type' => 'regex',
            'matches' => [
                '/^206:\s*2000:\s*The provided MSISDN is not correct\.$/',
            ],
            'modifiedMessage' => 'Incorrect mobile number.',
        ],
        [
            'type' => 'regex',
            'matches' => [
                '/^206:\s*206:\s*Internal error encountered\. Please contact administrator\.$/',
            ],
            'modifiedMessage' => 'Internal error encountered. Please contact administrator.',
        ],
        [
            'type' => 'regex',
            'matches' => [
                '/^206:\s*230:\s*Link timeout\.$/',
            ],
            'modifiedMessage' => 'The request to the operator took too long and timed out. Please try again.',
        ],
        [
            'type' => 'regex',
            'matches' => [
                '/^206:\s*31010:\s*You did not succeed to buy air time\.$/',
            ],
            'modifiedMessage' => 'You did not succeed to buy air time.',
        ],
        [
            'type' => 'regex',
            'matches' => [
                '/^206:\s*44010:\s*The service is currently down, please try again later\.$/',
            ],
            'modifiedMessage' => 'The service is currently down, please try again later.',
        ],
        [
            'type' => 'regex',
            'matches' => [
                '/^206:\s*4596:\s*You do not have sufficient credit for this transaction\.$/',
            ],
            'modifiedMessage' => 'Internal error encountered. Please contact administrator.',
        ],
        [
            'type' => 'regex',
            'matches' => [
                '/^206:\s*500:\s*Link Error\.$/',
            ],
            'modifiedMessage' => 'Technical error.',
        ],
        [
            'type' => 'regex',
            'matches' => [
                '/^206:\s*500:\s*Link is down\.$/',
            ],
            'modifiedMessage' => 'Technical error.',
        ],
        [
            'type' => 'regex',
            'matches' => [
                '/^206:\s*Access to Link denied\.$/',
            ],
            'modifiedMessage' => 'Unable to access operator link. Try again.',
        ],
        [
            'type' => 'regex',
            'matches' => [
                '/^206:\s*5012:\s*You provided an invalid product\. Please try again with a valid product\.$/',
            ],
            'modifiedMessage' => 'Invalid transfer value.',
        ],
        [
            'type' => 'regex',
            'matches' => [
                '/^206:\s*5012:\s*You provided an invalid amount\. Please try again with a valid amount\.$/',
            ],
            'modifiedMessage' => 'Invalid transfer amount.',
        ],
        [
            'type' => 'regex',
            'matches' => [
                '/^206:\s*505:\s*Subscriber account not found\.$/',
            ],
            'modifiedMessage' => 'Subscriber account not found.',
        ],
        [
            'type' => 'regex',
            'matches' => [
                '/^206:\s*616:\s*Error during the validation phase\.$/',
            ],
            'modifiedMessage' => 'Error during the validation phase.',
        ],
        [
            'type' => 'regex',
            'matches' => [
                '/(250:\s*210:\s*)?Order\s+request\s+of\s+Tk\s+\S+\s*BDT\s*for\s*mobile\s+no\.\s*\S+\s*,\s*transaction\s*ID\s*\S+\s*is\s+successful\.\s*Your\s+account\s+balance\s+is\s+TK\s+\S+\./u',
            ],
            'modifiedMessage' => 'Failed at operator end and refunded.',
        ],
        [
            'type' => 'exact',
            'matches' => [
                'Empty reply from server',
            ],
            'modifiedMessage' => 'Empty reply from server.',
        ],
        [
            'type' => 'exact',
            'matches' => [
                'No response found',
            ],
            'modifiedMessage' => 'Technical issue.',
        ],
        [
            'type' => 'exact',
            'matches' => [
                'Recv failure: Connection reset by peer',
            ],
            'modifiedMessage' => 'Connection issue.',
        ],
        [
            'type' => 'exact',
            'matches' => [
                'unable to get response',
            ],
            'modifiedMessage' => 'Technical issue.',
        ],
        [
            'type' => 'exact',
            'matches' => [
                '25034: Invalid user',
            ],
            'modifiedMessage' => 'Invalid user.',
        ],
        [
            'type' => 'regex',
            'matches' => [
                '/^6011:\s*Your current request to transfer \d+\s*TAKA for product EasyLoad cannot be processed as your current balance is \d+\s*TAKA\.and you should have minimum remaining balance of \d+\s*TAKA$/',
            ],
            'modifiedMessage' => 'Internal error encountered. Please contact administrator.',
        ],
        [
            'type' => 'exact',
            'matches' => [
                '8514: System upgradation activity is ongoing. Please try later.',
            ],
            'modifiedMessage' => 'System upgradation activity is ongoing. Please try later.',
        ],
        [
            'type' => 'exact',
            'matches' => [
                'Can Not Be Processed',
            ],
            'modifiedMessage' => 'Processing error.',
        ],
        [
            'type' => 'regex',
            'matches' => [
                '/^(206:\s*)?17007:.*?(Your request cannot be processed at this time, please try again later.).*$/',
                '/^(206:\s*)?17017:.*?(Your request cannot be processed at this time, please try again later.).*$/',
            ],
            'modifiedMessage' => 'Your request cannot be processed at this time, please try again later.',
        ],
        [
            'type' => 'regex',
            'matches' => [
                '/(201:\s*)?Successfully\s+TopUp\s+Tk\s+\d+(\.\d+)?\s+to\s+\d{10,14},\s+transaction\s+ID\s+\w+(\.\w+){2}\s+Your\s+current\s+balance\s+is\s+Tk\s+\d+(\.\d+)?/u',
            ],
            'modifiedMessage' => 'Order Successful',
        ],

    ],
];
