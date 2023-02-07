<?php
/**
 * EFtpAssetManager class file.
 *
 * @author Rodolfo González González
 * @link http://www.yiiframework.com/
 * @copyright Copyright &copy; 2008 Rodolfo González González.
 * @license The 3-Clause BSD License
 *
 * Copyright © 2008, Rodolfo González González.
 *
 * Redistribution and use in source and binary forms, with or without
 * modification, are permitted provided that the following conditions are met:
 *
 * 1. Redistributions of source code must retain the above copyright notice, this
 * list of conditions and the following disclaimer.
 *
 * 2. Redistributions in binary form must reproduce the above copyright notice,
 * this list of conditions and the following disclaimer in the documentation
 * and/or other materials provided with the distribution.
 *
 * 3. Neither the name of the copyright holder nor the names of its
 * contributors may be used to endorse or promote products derived from
 * this software without specific prior written permission.
 *
 * THIS SOFTWARE IS PROVIDED BY THE COPYRIGHT HOLDERS AND CONTRIBUTORS "AS IS"
 * AND ANY EXPRESS OR IMPLIED WARRANTIES, INCLUDING, BUT NOT LIMITED TO, THE
 * IMPLIED WARRANTIES OF MERCHANTABILITY AND FITNESS FOR A PARTICULAR PURPOSE ARE
 * DISCLAIMED. IN NO EVENT SHALL THE COPYRIGHT HOLDER OR CONTRIBUTORS BE LIABLE
 * FOR ANY DIRECT, INDIRECT, INCIDENTAL, SPECIAL, EXEMPLARY, OR CONSEQUENTIAL
 * DAMAGES (INCLUDING, BUT NOT LIMITED TO, PROCUREMENT OF SUBSTITUTE GOODS OR
 * SERVICES; LOSS OF USE, DATA, OR PROFITS; OR BUSINESS INTERRUPTION) HOWEVER
 * CAUSED AND ON ANY THEORY OF LIABILITY, WHETHER IN CONTRACT, STRICT LIABILITY,
 * OR TORT (INCLUDING NEGLIGENCE OR OTHERWISE) ARISING IN ANY WAY OUT OF THE USE
 * OF THIS SOFTWARE, EVEN IF ADVISED OF THE POSSIBILITY OF SUCH DAMAGE.
 */

/**
 * EFtpAssetManager extends CAssetManager to allow the use of PHP's wrappers
 * ftp:// o http:// to store the assets. This is useful in a webfarm scenario
 * where the webserver is the frontend to a number of PHP FastCGI servers which
 * in other way would need to store the assets in a central server shared with
 * NFS or some other shared filesystem, or in every server.
 *
 * @author rodolfo
 */
class EFtpAssetManager extends CAssetManager
{
   /**
    * @var boolean whether to lock assets so they won't be published. This is
    * useful if the assets are published to a remote server and you know they
    * won't change between calls. If you need to republish the asset simply
    * remove the associate locks in the runtime/assets directory.
    */
   public $lockAssets = false;

   /**
    * @var string the path where to place the locks if lockAssets=true,
    */
   public $lockPath = null;

   /**
    * @var string the FTP server IP address or hostname.
    */
   public $host = '';

   /**
    * @var string the path in the server where assets will be stored.
    */
   protected $_basePath = '';

   /**
    * @var boolean whether to use a remote (FTP/FTPS) repository to store the
    * assets or not. This is used only internally.
    */
   protected $_remoteAssets = false;

	/**
	 * Sets the root directory storing published asset files. This could use the
    * ftp:// or ftps:// wrappers.
    *
    * @see See {@link http://php.net/manual/en/wrappers.ftp.php}
    *
	 * @param string $value the root directory storing published asset files
	 * @throws CException if the base path is invalid
	 */
	public function setBasePath($value)
	{
		if(($basePath=realpath($value))!==false && is_dir($basePath) && is_writable($basePath)) {
			$this->_basePath = $basePath;
      }
      elseif (strpos($value, 'ftp://')==0 || strpos($value, 'ftps://')==0) {
         $this->_basePath = $value;
         $this->_remoteAssets = true;
      }
		else {
			throw new CException(Yii::t('yii','CAssetManager.basePath "{path}" is invalid. Please make sure the directory exists and is writable by the Web server process.',
				array('{path}'=>$value)));
      }
	}

   public function getBaseUrl()
   {
      if ($this->_baseUrl === null) {
         $this->_baseUrl = (Yii::app()->request->isSecureConnection?'https://':'http://').$this->host.'/'.$this->path;
      }
      return $this->_baseUrl;
   }

	/**
	 * Publishes a file or a directory to a local directoy or FTP(S) server.
	 * This method will copy the specified asset to a web accessible directory
	 * and return the URL for accessing the published asset.
	 * <ul>
	 * <li>If the asset is a file, its file modification time will be checked
	 * to avoid unnecessary file copying;</li>
	 * <li>If the asset is a directory, all files and subdirectories under it will
	 * be published recursively. Note, in case $forceCopy is false the method only checks the
	 * existence of the target directory to avoid repetitive copying.</li>
	 * </ul>
	 *
	 * Note: On rare scenario, a race condition can develop that will lead to a
	 * one-time-manifestation of a non-critical problem in the creation of the directory
	 * that holds the published assets. This problem can be avoided altogether by 'requesting'
	 * in advance all the resources that are supposed to trigger a 'publish()' call, and doing
	 * that in the application deployment phase, before system goes live. See more in the following
	 * discussion: http://code.google.com/p/yii/issues/detail?id=2579
	 *
	 * @param string $path the asset (file or directory) to be published
	 * @param boolean $hashByName whether the published directory should be named as the hashed basename.
	 * If false, the name will be the hash taken from dirname of the path being published and path mtime.
	 * Defaults to false. Set true if the path being published is shared among
	 * different extensions.
	 * @param integer $level level of recursive copying when the asset is a directory.
	 * Level -1 means publishing all subdirectories and files;
	 * Level 0 means publishing only the files DIRECTLY under the directory;
	 * level N means copying those directories that are within N levels.
	 * @param boolean $forceCopy whether we should copy the asset file or directory even if it is already published before.
	 * This parameter is set true mainly during development stage when the original
	 * assets are being constantly changed. The consequence is that the performance
	 * is degraded, which is not a concern during development, however.
	 * This parameter has been available since version 1.1.2.
	 * @return string an absolute URL to the published asset
	 * @throws CException if the asset to be published does not exist.
	 */
	public function publish($path,$hashByName=false,$level=-1,$forceCopy=false)
	{
		if (isset($this->_published[$path])) {
			return $this->_published[$path];
      }
		else if (($src=realpath($path)) !== false) {
         if ($this->lockAssets) {
            if (!is_dir($this->lockPath)) {
               mkdir($this->lockPath);
            }
         }

			if (is_file($src)) {
				$dir = $this->hash($hashByName ? basename($src) : dirname($src));
				$fileName = basename($src);

            if (($this->lockAssets && !file_exists($this->lockPath.DIRECTORY_SEPARATOR.$fileName.'.lock')) || !$this->lockAssets) {
               $dstDir = $this->getBasePath().DIRECTORY_SEPARATOR.$dir;
               $dstFile = $dstDir.DIRECTORY_SEPARATOR.$fileName;

               if ($this->linkAssets && !$this->_remoteAssets) {
                  if (!is_file($dstFile)) {
                     if (!is_dir($dstDir)) {
                        mkdir($dstDir);
                        @chmod($dstDir, $this->newDirMode);
                     }
                     symlink($src,$dstFile);
                  }
               }
               else if (@filemtime($dstFile) < @filemtime($src)) {
                  if (!is_dir($dstDir)) {
                     mkdir($dstDir);
                     @chmod($dstDir, $this->newDirMode);
                  }
                  copy($src, $dstFile);
                  @chmod($dstFile, $this->newFileMode);
               }

               if ($this->lockAssets) {
                  @touch($this->lockPath.DIRECTORY_SEPARATOR.$fileName.'.lock');
               }
            }

				return $this->_published[$path]=$this->getBaseUrl()."/$dir/$fileName";
			}
			else if (is_dir($src)) {
				$dir = $this->hash($hashByName ? basename($src) : $src);

            if (($this->lockAssets && !file_exists($this->lockPath.DIRECTORY_SEPARATOR.$dir.'.lock')) || !$this->lockAssets) {
               $dstDir = $this->getBasePath().DIRECTORY_SEPARATOR.$dir;

               if ($this->linkAssets && !$this->_remoteAssets) {
                  if (!is_dir($dstDir)) {
                     symlink($src, $dstDir);
                  }
               }
               else if (!is_dir($dstDir) || $forceCopy) {
                  self::copyDirectory($src, $dstDir, array(
                     'exclude'=>$this->excludeFiles,
                     'level'=>$level,
                     'newDirMode'=>$this->newDirMode,
                     'newFileMode'=>$this->newFileMode,
                     'isRemote'=>$this->_remoteAssets,
                  ));
               }

               if ($this->lockAssets) {
                  @touch($this->lockPath.DIRECTORY_SEPARATOR.$dir.'.lock');
               }
            }

				return $this->_published[$path]=$this->getBaseUrl().'/'.$dir;
			}
		}
		throw new CException(Yii::t('yii','The asset "{asset}" to be published does not exist.',
			array('{asset}'=>$path)));
	}

   //***************************************************************************

	/**
	 * Copies a directory recursively as another.
	 * If the destination directory does not exist, it will be created.
	 * @param string $src the source directory
	 * @param string $dst the destination directory
	 * @param array $options options for directory copy. Valid options are:
	 * <ul>
	 * <li>fileTypes: array, list of file name suffix (without dot). Only files with these suffixes will be copied.</li>
	 * <li>exclude: array, list of directory and file exclusions. Each exclusion can be either a name or a path.
	 * If a file or directory name or path matches the exclusion, it will not be copied. For example, an exclusion of
	 * '.svn' will exclude all files and directories whose name is '.svn'. And an exclusion of '/a/b' will exclude
	 * file or directory '$src/a/b'. Note, that '/' should be used as separator regardless of the value of the DIRECTORY_SEPARATOR constant.
	 * </li>
	 * <li>level: integer, recursion depth, default=-1.
	 * Level -1 means copying all directories and files under the directory;
	 * Level 0 means copying only the files DIRECTLY under the directory;
	 * level N means copying those directories that are within N levels.
 	 * </li>
	 * </ul>
	 */
	public static function copyDirectory($src,$dst,$options=array())
	{
		$fileTypes=array();
		$exclude=array();
		$level=-1;
		extract($options);
		self::copyDirectoryRecursive($src, $dst, '', $fileTypes, $exclude, $level, $options);
	}

   /**
	 * Copies a directory.
	 * This method is mainly used by {@link copyDirectory}.
	 * @param string $src the source directory
	 * @param string $dst the destination directory
	 * @param string $base the path relative to the original source directory
	 * @param array $fileTypes list of file name suffix (without dot). Only files with these suffixes will be copied.
	 * @param array $exclude list of directory and file exclusions. Each exclusion can be either a name or a path.
	 * If a file or directory name or path matches the exclusion, it will not be copied. For example, an exclusion of
	 * '.svn' will exclude all files and directories whose name is '.svn'. And an exclusion of '/a/b' will exclude
	 * file or directory '$src/a/b'. Note, that '/' should be used as separator regardless of the value of the DIRECTORY_SEPARATOR constant.
	 * @param integer $level recursion depth. It defaults to -1.
	 * Level -1 means copying all directories and files under the directory;
	 * Level 0 means copying only the files DIRECTLY under the directory;
	 * level N means copying those directories that are within N levels.
	 * @param array $options additional options. The following options are supported:
	 * newDirMode - the permission to be set for newly copied directories (defaults to 0777);
	 * newFileMode - the permission to be set for newly copied files (defaults to the current environment setting).
	 */
	protected static function copyDirectoryRecursive($src, $dst, $base, $fileTypes, $exclude, $level, $options)
	{
		if(!is_dir($dst)) {
			mkdir($dst);
      }
		if(isset($options['newDirMode'])) {
			@chmod($dst, $options['newDirMode']);
      }
		else {
			@chmod($dst, 0777);
      }
		$folder = opendir($src);
		while (($file=readdir($folder))!==false) {
			if ($file==='.' || $file==='..') {
				continue;
         }
			$path = $src.DIRECTORY_SEPARATOR.$file;
			$isFile = is_file($path);
			if (self::validatePath($base, $file, $isFile, $fileTypes, $exclude)) {
				if ($isFile) {
               if (isset($options['isRemote']) && $options['isRemote']===true) {
                  self::remoteCopy($path, $dst, $file);
               }
               else {
                  copy($path, $dst.DIRECTORY_SEPARATOR.$file);
               }
   				if (isset($options['newFileMode'])) {
						@chmod($dst.DIRECTORY_SEPARATOR.$file, $options['newFileMode']);
               }
				}
				else if($level) {
					self::copyDirectoryRecursive($path, $dst.DIRECTORY_SEPARATOR.$file, $base.'/'.$file, $fileTypes, $exclude, $level-1, $options);
            }
			}
		}
		closedir($folder);
	}

   /**
	 * Validates a file or directory.
	 * @param string $base the path relative to the original source directory
	 * @param string $file the file or directory name
	 * @param boolean $isFile whether this is a file
	 * @param array $fileTypes list of file name suffix (without dot). Only files with these suffixes will be copied.
	 * @param array $exclude list of directory and file exclusions. Each exclusion can be either a name or a path.
	 * If a file or directory name or path matches the exclusion, it will not be copied. For example, an exclusion of
	 * '.svn' will exclude all files and directories whose name is '.svn'. And an exclusion of '/a/b' will exclude
	 * file or directory '$src/a/b'. Note, that '/' should be used as separator regardless of the value of the DIRECTORY_SEPARATOR constant.
	 * @return boolean whether the file or directory is valid
	 */
	protected static function validatePath($base, $file, $isFile, $fileTypes, $exclude)
	{
		foreach ($exclude as $e) {
			if ($file===$e || strpos($base.'/'.$file,$e)===0) {
				return false;
         }
		}
		if (!$isFile || empty($fileTypes)) {
			return true;
      }
		if (($type=pathinfo($file, PATHINFO_EXTENSION))!=='') {
			return in_array($type,$fileTypes);
      }
		else {
			return false;
      }
	}

   /**
    * Does a FTP copy.
    *
    * @param string $src the source path.
    * @param string $dst the destination FTP URI, without filename.
    * @param string $file the filename to copy.
    * @throws CException
    */
   protected static function remoteCopy($src, $dst, $file)
   {
      if (PHP_VERSION_ID < 50300) {
         preg_match('%^(?=[^&])(?:(?<scheme>[^:/?#]+):)?(?://(?<login>[^:]*):)?(?:(?<password>[^@]*)@)?(?:(?<host>[^/]*)/)?(?<path>[^?#]*)(?:\?(?<query>[^#]*))?(?:#(?<fragment>.*))?%i', $dst, $matches);

         if ($matches['scheme'] !== 'ftp' && $matches['scheme'] !== 'ftps') {
            throw new CException('Currently only FTP transfers are implemented.');
         }

         $conn_id = ftp_connect($matches['host']);
         $login_result = ftp_login($conn_id, $matches['login'], $matches['password']);

         if ((!$conn_id) || (!$login_result)) {
            throw new CException("FTP connection has failed.");
         }

         $upload = ftp_put($conn_id, $matches['path'].DIRECTORY_SEPARATOR.$file, $src, FTP_BINARY);

         if (!$upload) {
            throw new CException("FTP upload has failed.");
         }

         ftp_close($conn_id);
      }
      else {
         copy($src, $dst.DIRECTORY_SEPARATOR.$file);
      }
   }
}