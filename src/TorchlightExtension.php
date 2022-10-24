<?php

namespace A17\PhpTorch;

use Illuminate\Support\Str;

class TorchlightExtension extends \Torchlight\Jigsaw\TorchlightExtension
{
    protected function hookIntoMarkdownParser(): void
    {
        $this->container['markdownParser']->code_block_content_func = function ($code, $language) {
            // We have to undo the escaping that the Jigsaw Markdown handler does.
            // See MarkdownHandler->getEscapedMarkdownContent.
            $code = strtr($code, [
                "<{{'?php'}}" => '<?php',
                "{{'@'}}" => '@',
                '@{{' => '{{',
                '@{!!' => '{!!',
            ]);

            // Handle our customizations.
            if (Str::startsWith($language, 'phptorch')) {
                $containsCode = Str::contains($code, '##CODE##');

                if ($containsCode) {
                    $data = json_decode(Str::before($code, '##CODE##'), flags: JSON_THROW_ON_ERROR);

                    $code = Str::after($code, '##CODE##');

                    $highlighter = Highlight::fromCode(trim($code));
                } else {
                    $data = json_decode($code, flags: JSON_THROW_ON_ERROR);

                    if (Str::startsWith($data->file, '/')) {
                        $path = $data->file;
                    } else {
                        $path = getcwd() . '/' . $data->file;
                    }

                    $highlighter = Highlight::new($path);
                }

                if ($data->language ?? false) {
                    $language = $data->language;
                } elseif ($data->file ?? false) {
                    $language = Str::afterLast($data->file, '.');
                } else {
                    $language = 'php';
                }

                foreach ($data as $key => $value) {
                    if ($key === 'file' || $key === 'language') {
                        continue;
                    }

                    if (method_exists($highlighter, $key)) {
                        if (is_object($value)) {
                            $highlighter->{$key}(...(array)$value);
                        } elseif (is_array($value) && is_object($value[0])) {
                            $highlighter->{$key}(...(array)$value[0]);
                        } else {
                            $highlighter->{$key}($value);
                        }
                    }
                }

                $code = (string)$highlighter;
            }

            $block = $this->createBlock($code, $language);

            // Add it to our tracker.
            $this->addBlock($block, $markdown = true);

            // Leave our placeholder for replacing later.
            return $block->placeholder();
        };
    }
}
