# GetFavicon

用 PHP 获取网站 favicon 的API，可用于美化网站外链显示效果。

## 部署

### 使用 [`Vercel`](https://github.com/vercel-community/php) 部署

<a href="https://vercel.com/new/clone?repository-url=https://github.com/deploybox/favicon-2&project-name=favicon&repository-name=favicon"><img src="https://vercel.com/button"></a>

### Nginx 等

将 `api` 目录设置为根目录，或者将 `index.php` 放置在网站根目录下即可。

### Docker

```bash
docker run -d --name get-favicon -p 80:80 lufeidot/get-favicon:latest
```

## 使用

`https://favicon-2.vercel.app/?url=域名`

```
https://favicon-2.vercel.app/?url=example.com
https://favicon-2.vercel.app/?url=http://example.com
https://favicon-2.vercel.app/?url=https://example.com
```

## 示例

- [x] 百度 ![](https://favicon-2.vercel.app/?url=www.baidu.com)
- [x] 维基百科 ![](https://favicon-2.vercel.app/?url=https://www.wikipedia.org)
- [x] segmentfault ![](https://favicon-2.vercel.app/?url=segmentfault.com)
- [x] GitHub ![](https://favicon-2.vercel.app/?url=github.com)

## LICENSE

MIT
