<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Class Article
 */
class Article extends Model
{
    /**
     * @var string
     */
    protected $table = 'article';

    /**
     * @var string
     */
    protected $primaryKey = 'article_id';

    /**
     * @var bool
     */
    public $timestamps = false;

    /**
     * @var array
     */
    protected $hidden = ['article_id', 'cat_id', 'is_open', 'open_type', 'author_email', 'article_type'];

    /**
     * @var array
     */
    protected $visible = [];

    /**
     * @var array
     */
    protected $appends = ['id', 'url', 'album', 'amity_time'];

    /**
     * @var array
     */
    protected $fillable = [
        'cat_id',
        'title',
        'content',
        'author',
        'author_email',
        'keywords',
        'article_type',
        'is_open',
        'add_time',
        'file_url',
        'open_type',
        'link',
        'description'
    ];

    /**
     * @var array
     */
    protected $guarded = [];

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasOne
     */
    public function extend()
    {
        return $this->hasOne('App\Models\ArticleExtend', 'article_id', 'article_id');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\HasMany
     */
    public function comment()
    {
        return $this->hasMany('App\Models\Comment', 'id_value', 'article_id');
    }

    /**
     * @return \Illuminate\Database\Eloquent\Relations\BelongsToMany
     */
    public function goods()
    {
        return $this->belongsToMany('App\Models\Goods', 'goods_article', 'article_id', 'goods_id');
    }

    /**
     * @return string
     */
    public function getAddTimeAttribute()
    {
        return local_date('Y-m-d', $this->attributes['add_time']);
    }

    /**
     * @return string
     */
    public function getAmityTimeAttribute()
    {
        return friendlyDate(strtotime(local_date('Y-m-d H:i:s', $this->attributes['add_time'])), 'moremohu');
    }

    /**
     * @return mixed
     */
    public function getIdAttribute()
    {
        return $this->attributes['article_id'];
    }

    /**
     * @return array
     */
    public function getAlbumAttribute()
    {
        $pattern = "/<[img|IMG].*?src=[\'|\"](.*?(?:[\.gif|\.jpg|\.png|\.bmp|\.jpeg]))[\'|\"].*?[\/]?>/";
        preg_match_all($pattern, $this->attributes['content'], $match);
        $album = [];
        if (count($match[1]) > 0) {
            foreach ($match[1] as $img) {
                if (strtolower(substr($img, 0, 4)) != 'http') {
                    $realpath = mb_substr($img, stripos($img, 'images/'));
                    $album[] = get_image_path($realpath);
                }
            }
        }
        // 超过三图则取前三
        if (count($album) > 3) {
            $album = array_slice($album, 0, 3);
        }
        return $album;
    }

    /**
     * @return string
     */
    public function getUrlAttribute()
    {
        return url('article/index/detail', ['id' => $this->attributes['article_id']]);
    }

    /**
     * @return mixed
     */
    public function getCatId()
    {
        return $this->cat_id;
    }

    /**
     * @return mixed
     */
    public function getTitle()
    {
        return $this->title;
    }

    /**
     * @return mixed
     */
    public function getContent()
    {
        return $this->content;
    }

    /**
     * @return mixed
     */
    public function getAuthor()
    {
        return $this->author;
    }

    /**
     * @return mixed
     */
    public function getAuthorEmail()
    {
        return $this->author_email;
    }

    /**
     * @return mixed
     */
    public function getKeywords()
    {
        return $this->keywords;
    }

    /**
     * @return mixed
     */
    public function getArticleType()
    {
        return $this->article_type;
    }

    /**
     * @return mixed
     */
    public function getIsOpen()
    {
        return $this->is_open;
    }

    /**
     * @return mixed
     */
    public function getAddTime()
    {
        return $this->add_time;
    }

    /**
     * @return mixed
     */
    public function getFileUrl()
    {
        return $this->file_url;
    }

    /**
     * @return mixed
     */
    public function getOpenType()
    {
        return $this->open_type;
    }

    /**
     * @return mixed
     */
    public function getLink()
    {
        return $this->link;
    }

    /**
     * @return mixed
     */
    public function getDescription()
    {
        return $this->description;
    }


    
    /**
     * @param $value
     * @return $this
     */
    public function setCatId($value)
    {
        $this->cat_id = $value;
        return $this;
    }

    /**
     * @param $value
     * @return $this
     */
    public function setTitle($value)
    {
        $this->title = $value;
        return $this;
    }

    /**
     * @param $value
     * @return $this
     */
    public function setContent($value)
    {
        $this->content = $value;
        return $this;
    }

    /**
     * @param $value
     * @return $this
     */
    public function setAuthor($value)
    {
        $this->author = $value;
        return $this;
    }

    /**
     * @param $value
     * @return $this
     */
    public function setAuthorEmail($value)
    {
        $this->author_email = $value;
        return $this;
    }

    /**
     * @param $value
     * @return $this
     */
    public function setKeywords($value)
    {
        $this->keywords = $value;
        return $this;
    }

    /**
     * @param $value
     * @return $this
     */
    public function setArticleType($value)
    {
        $this->article_type = $value;
        return $this;
    }

    /**
     * @param $value
     * @return $this
     */
    public function setIsOpen($value)
    {
        $this->is_open = $value;
        return $this;
    }

    /**
     * @param $value
     * @return $this
     */
    public function setAddTime($value)
    {
        $this->add_time = $value;
        return $this;
    }

    /**
     * @param $value
     * @return $this
     */
    public function setFileUrl($value)
    {
        $this->file_url = $value;
        return $this;
    }

    /**
     * @param $value
     * @return $this
     */
    public function setOpenType($value)
    {
        $this->open_type = $value;
        return $this;
    }

    /**
     * @param $value
     * @return $this
     */
    public function setLink($value)
    {
        $this->link = $value;
        return $this;
    }

    /**
     * @param $value
     * @return $this
     */
    public function setDescription($value)
    {
        $this->description = $value;
        return $this;
    }
}
