<?php
/**
 * FusionForge nouveau
 *
 * Translated from gwicke's previous TAL template version to remove
 * dependency on PHPTAL.
 *
 * @todo document
 * @file
 * @ingroup Skins
 */

require_once MEDIAWIKI_BASE_DIR.'/MediawikiManager.class.php';

if ( !defined( 'MEDIAWIKI' ) ) {
	die( -1 );
}

/**
 * Inherit main code from SkinTemplate, set the CSS and template filter.
 * @todo document
 * @ingroup Skins
 */
class SkinTuleap123 extends SkinTemplate {

    CONST MEDIAWIKI_URL = '/\/plugins\/mediawiki\/wiki\/(.*)\/index.php\//';

    /** Using fusionforge. */
    var $skinname = 'tuleap123', $stylename = 'tuleap123',
            $template = 'Tuleap123Template', $useHeadElement = true;

    function setupTemplate( $classname, $repository = false, $cache_dir = false ) {
            $tc = new $classname();
            $tc->params = array();
            if (($tc->project = $project =
                group_get_object_by_name($GLOBALS['fusionforgeproject']))) {
                    $tc->params['group'] = $GLOBALS['group_id'] =
                        $project->getID();
                    $tc->params['toptab'] = 'plugin_mediawiki';
                    //$page_name = substr($_SERVER['REQUEST_URI'], (strpos($_SERVER['REQUEST_URI'], 'index.php/') + strlen('index.php/')), strlen($_SERVER['REQUEST_URI']));
                    $page_name = preg_replace(self::MEDIAWIKI_URL,'',$_SERVER['REQUEST_URI']);
                    $tc->params['title'] = 'Mediawiki-' . $page_name;
            }

            return $tc;
    }

    /**
     * @param $out OutputPage
     */
    function setupSkinUserCss( OutputPage $out ) {
            /* add Tuleap styles */
            foreach ($GLOBALS['HTML']->getAllStyleSheets() as $sheet) {
                    $out->addStyle($sheet['css'], $sheet['media']);
            }

            parent::setupSkinUserCss( $out );

            $out->addModuleStyles( array( 'mediawiki.skinning.interface', 'skins.monobook.styles' ) );

            // TODO: Migrate all of these
            $out->addStyle( 'Tuleap123/main.css', 'screen');
            $out->addStyle( 'Tuleap123/TuleapSkin.css', 'screen');
            $out->addStyle( 'Tuleap123/IE60Fixes.css', 'screen', 'IE 6' );
            $out->addStyle( 'Tuleap123/IE70Fixes.css', 'screen', 'IE 7' );
    }

    function addToBodyAttributes( $out, &$bodyAttrs ) {
        parent::addToBodyAttributes( $out, $bodyAttrs );
        $current_user  = UserManager::instance()->getCurrentUser();
        $sidebar_state = $current_user->getPreference('sidebar_state');

        if (! $sidebar_state) {
            $sidebar_state = 'sidebar-expanded';
        }

        $bodyAttrs['class'] .= ' has_sidebar ' . $sidebar_state;

        $theme_manager    = new ThemeManager();
        $user_theme       = $current_user->getTheme();
        $prefered_variant = $current_user->getPreference('theme_variant');

        if ($this->hasVariant($user_theme)) {
            $bodyAttrs['class'] .= ' ' . $prefered_variant;
        } elseif (! $theme_manager->isThemeValid($user_theme) && $this->hasVariant(ForgeConfig::get('sys_themedefault'))) {
            if ($prefered_variant) {
                $bodyAttrs['class'] .= ' ' . $prefered_variant;
            } else {
                $bodyAttrs['class'] .= ' ' . ForgeConfig::get('sys_default_theme_variant');
            }
        }
    }

    private function hasVariant($theme)
    {
        return $theme === 'FlamingParrot' || $theme === 'BurningParrot';
    }
}

/**
 * @todo document
 * @ingroup Skins
 */
class Tuleap123Template extends BaseTemplate {

        public $project = false;

	/**
	 * Template filter callback for FusionForge skin.
	 * Takes an associative array of data set from a SkinTemplate-based
	 * class, and a wrapper for MediaWiki's localization database, and
	 * outputs a formatted page.
	 *
	 */
	public function execute() {
            global $wgHtml5;
            // Suppress warnings to prevent notices about missing indexes in $this->data
            wfSuppressWarnings();

            $this->html( 'headelement' );

            echo "\n<!-- FUSIONFORGE BodyHeader BEGIN -->\n";

            if ($this->isCompatibilityViewEnabled()) {
                $this->addForgeBackLinksToSidebar();
            } else  {
                $GLOBALS['HTML']->header($this->params);
                if ($this->IsUserAdmin()) {
                    echo '<ul class="nav nav-pills toolbar"><li><a href="/plugins/mediawiki/forge_admin?group_id='.$GLOBALS['group']->getId().'">'.$GLOBALS['Language']->getText('global', 'Administration').'</a></li></ul>';
                }
            }

            echo "<div id=\"ff-mw-wrapper\"><div style=\"font-size:x-small;\">\n";
            echo "<!-- FUSIONFORGE BodyHeader END -->\n";

?><div id="globalWrapper">
<div id="column-content"><div id="content" class="mw-body-primary" role="main">
	<a id="top"></a>
	<?php if ( $this->data['sitenotice'] ) { ?><div id="siteNotice"><?php $this->html( 'sitenotice' ) ?></div><?php } ?>

	<h1 id="firstHeading" class="firstHeading" lang="<?php
		$this->data['pageLanguage'] = $this->getSkin()->getTitle()->getPageViewLanguage()->getHtmlCode();
		$this->text( 'pageLanguage' );
	?>"><span dir="auto"><?php $this->html( 'title' ) ?></span></h1>
	<div id="bodyContent" class="mw-body">
		<div id="siteSub"><?php $this->msg( 'tagline' ) ?></div>
		<div id="contentSub"<?php $this->html( 'userlangattributes' ) ?>><?php $this->html( 'subtitle' ) ?></div>
<?php if ( $this->data['undelete'] ) { ?>
		<div id="contentSub2"><?php $this->html( 'undelete' ) ?></div>
<?php } ?><?php if ( $this->data['newtalk'] ) { ?>
		<div class="usermessage"><?php $this->html( 'newtalk' ) ?></div>
<?php } ?>
		<div id="jump-to-nav" class="mw-jump"><?php $this->msg( 'jumpto' ) ?> <a href="#column-one"><?php $this->msg( 'jumptonavigation' ) ?></a><?php $this->msg( 'comma-separator' ) ?><a href="#searchInput"><?php $this->msg( 'jumptosearch' ) ?></a></div>

		<!-- start content -->
<?php $this->html( 'bodytext' ) ?>
		<?php if ( $this->data['catlinks'] ) { $this->html( 'catlinks' ); } ?>
		<!-- end content -->
		<?php if ( $this->data['dataAfterContent'] ) { $this->html( 'dataAfterContent' ); } ?>
		<div class="visualClear"></div>
	</div>
</div></div>
<div id="column-one"<?php $this->html( 'userlangattributes' ) ?>>
	<h2><?php $this->msg( 'navigation-heading' ) ?></h2>
<?php $this->cactions(); ?>
	<div class="portlet" id="p-personal" role="navigation">
		<h3><?php $this->msg( 'personaltools' ) ?></h3>
		<div class="pBody">
			<ul<?php $this->html( 'userlangattributes' ) ?>>
<?php		foreach ( $this->getPersonalTools() as $key => $item ) { ?>
				<?php echo $this->makeListItem( $key, $item ); ?>

<?php		} ?>
			</ul>
		</div>
	</div>
	<?php $class_no_logo = (! $this->data['logopath']) ? 'no-logo' : ''; ?>
	<div class="portlet <?php echo $class_no_logo; ?>" id="p-logo" role="banner">
<?php
			echo Html::element( 'a', array(
				'href' => $this->data['nav_urls']['mainpage']['href'],
				'style' => "background-image: url({$this->data['logopath']});" )
				+ Linker::tooltipAndAccesskeyAttribs( 'p-logo' ) ); ?>

	</div>
<?php
	$this->renderPortals( $this->data['sidebar'] );
?>
</div><!-- end of the left (by default at least) column -->
<div class="visualClear"></div>
<?php
	$validFooterIcons = $this->getFooterIcons( "icononly" );
	$validFooterLinks = $this->getFooterLinks( "flat" ); // Additional footer links

	if ( count( $validFooterIcons ) + count( $validFooterLinks ) > 0 ) { ?>
<div id="footer" role="contentinfo"<?php $this->html( 'userlangattributes' ) ?>>
<?php
		$footerEnd = '</div>';
	} else {
		$footerEnd = '';
	}
	foreach ( $validFooterIcons as $blockName => $footerIcons ) { ?>
	<div id="f-<?php echo htmlspecialchars( $blockName ); ?>ico">
<?php foreach ( $footerIcons as $icon ) { ?>
		<?php echo $this->getSkin()->makeFooterIcon( $icon ); ?>

<?php }
?>
	</div>
<?php }

		if ( count( $validFooterLinks ) > 0 ) {
?>	<ul id="f-list">
<?php
			foreach ( $validFooterLinks as $aLink ) { ?>
		<li id="<?php echo $aLink ?>"><?php $this->html( $aLink ) ?></li>
<?php
			}
?>
	</ul>
<?php	}
echo $footerEnd;
?>

</div>
<?php
		$this->printTrail();
                echo "</div></div>\n";
                $GLOBALS['HTML']->footer($this->params);
		echo Html::closeElement( 'body' );
		echo Html::closeElement( 'html' );
		wfRestoreWarnings();
	} // end of execute() method

     private function IsUserAdmin() {
        $pfuser             = UserManager::instance()->getCurrentUser();
        $forge_user_manager = new User_ForgeUserGroupPermissionsManager(
            new User_ForgeUserGroupPermissionsDao()
        );
        $has_special_permission = $forge_user_manager->doesUserHavePermission(
            $pfuser,
            new User_ForgeUserGroupPermission_MediawikiAdminAllProjects()
        );

        return $pfuser->isMember($GLOBALS['group']->getId(), 'A') || $has_special_permission;
     }

     private function isUserAnonymous() {
         return UserManager::instance()->getCurrentUser()->isAnonymous();
     }

     private function isCompatibilityViewEnabled() {
        $manager = new MediawikiManager(new MediawikiDao());
        $project = $GLOBALS['group'];

        return $manager->isCompatibilityViewEnabled($project);
     }

     private function addForgeBackLinksToSidebar() {
        $forge_name    = forge_get_config('sys_fullname');
        $added_toolbox = array();

        if ($this->isUserAnonymous()) {
            $added_toolbox[] = array(
                'text' => $GLOBALS['Language']->getText('include_menu','login'),
                'href' => '/account/login.php?return_to='. $_SERVER['REQUEST_URI']
            );
        }

        $added_toolbox[] = array(
            'text' => $GLOBALS['Language']->getText('plugin_mediawiki', 'back_to_forge', array($forge_name)),
            'href' => '/projects/'.$GLOBALS['group']->getUnixName()
        );

        if ($this->IsUserAdmin()) {
            $added_toolbox []= array(
                'text' => $GLOBALS['Language']->getText('global', 'Administration'),
                'href' => '/plugins/mediawiki/forge_admin?group_id='.$GLOBALS['group']->getId()
            );
        }

        $this->data['sidebar'][$forge_name] = $added_toolbox;
     }

     protected function renderPortals( $sidebar ) {
		if ( !isset( $sidebar['SEARCH'] ) ) {
			$sidebar['SEARCH'] = true;
		}
		if ( !isset( $sidebar['TOOLBOX'] ) ) {
			$sidebar['TOOLBOX'] = true;
		}
		if ( !isset( $sidebar['LANGUAGES'] ) ) {
			$sidebar['LANGUAGES'] = true;
		}

                echo '<div class="tuleap-panel">';

		foreach ( $sidebar as $boxName => $content ) {
			if ( $content === false ) {
				continue;
			}

			if ( $boxName == 'SEARCH' ) {
                            if ($this->isCompatibilityViewEnabled()) {
                                $this->searchBox();
                            }
			} elseif ( $boxName == 'TOOLBOX' ) {
				$this->toolbox();
			} elseif ( $boxName == 'LANGUAGES' ) {
				$this->languageBox();
			} else {
				$this->customBox( $boxName, $content );
			}
		}

                echo '</div>';
	}

        function searchBox() {
		global $wgUseTwoButtonsSearchForm;
?>
	<div id="p-search" class="portlet">
		<h5><label for="searchInput"><?php $this->msg('search') ?></label></h5>
		<div id="searchBody" class="pBody">
			<form action="<?php $this->text('wgScript') ?>" id="searchform">
				<input type='hidden' name="title" value="<?php $this->text('searchtitle') ?>"/>
				<?php echo $this->makeSearchInput(array( "id" => "searchInput" )); ?>

				<?php echo $this->makeSearchButton("go", array( "id" => "searchGoButton", "class" => "searchButton" ));
				if ($wgUseTwoButtonsSearchForm) { ?>&#160;
				<?php echo $this->makeSearchButton("fulltext", array( "id" => "mw-searchButton", "class" => "searchButton" ));
                                } else { ?>

				<div><a href="<?php $this->text('searchaction') ?>" rel="search"><?php $this->msg('powersearch-legend') ?></a></div><?php
                                } ?>

			</form>
		</div>
	</div>
<?php
	}

	/**
	 * Prints the cactions bar.
	 * Shared between MonoBook and Modern
	 */
	function cactions() {
?>
	<div id="p-cactions" class="portlet" role="navigation">
		<h5><?php $this->msg('views') ?></h5>
		<div class="pBody">
			<ul><?php
				foreach ($this->data['content_actions'] as $key => $tab) {
					echo '
				' . $this->makeListItem( $key, $tab );
				} ?>

			</ul>
<?php		$this->renderAfterPortlet( 'cactions' ); ?>
		</div>
	</div>
<?php
	}
	/*************************************************************************************************/
	function toolbox() {
?>
	<div class="portlet" id="p-tb" role="navigation">
		<h5><?php $this->msg('toolbox') ?></h5>
		<div class="pBody">
			<ul>
<?php
		foreach ( $this->getToolbox() as $key => $tbitem ) { ?>
				<?php echo $this->makeListItem($key, $tbitem); ?>

<?php
		}
		wfRunHooks( 'MonoBookTemplateToolboxEnd', array( &$this ) );
		wfRunHooks( 'SkinTemplateToolboxEnd', array( &$this, true ) );
?>
			</ul>
<?php		$this->renderAfterPortlet( 'tb' ); ?>
		</div>
	</div>
<?php
	}

	/*************************************************************************************************/
	function languageBox() {
		if( $this->data['language_urls'] !== false ) {
?>
	<div id="p-lang" class="portlet" role="navigation">
		<h5<?php $this->html('userlangattributes') ?>><?php $this->msg('otherlanguages') ?></h5>
		<div class="pBody">
			<ul>
<?php		foreach ($this->data['language_urls'] as $key => $langlink) { ?>
				<?php echo $this->makeListItem($key, $langlink); ?>

<?php		} ?>
			</ul>
<?php		$this->renderAfterPortlet( 'lang' ); ?>
		</div>
	</div>
<?php
		}
	}

	/*************************************************************************************************/
        /**
	 * @param $bar string
	 * @param $cont array|string
	 */
	function customBox( $bar, $cont ) {
		$portletAttribs = array( 'class' => 'generated-sidebar portlet', 'id' => Sanitizer::escapeId( "p-$bar" ), 'role' => 'navigation' );
		$tooltip = Linker::titleAttrib( "p-$bar" );
		if ( $tooltip !== false ) {
			$portletAttribs['title'] = $tooltip;
		}
		echo '	' . Html::openElement( 'div', $portletAttribs );
?>

		<h5><?php $msg = wfMessage( $bar ); echo htmlspecialchars( $msg->exists() ? $msg->text() : $bar ); ?></h5>
		<div class='pBody'>
<?php   if ( is_array( $cont ) ) { ?>
			<ul>
<?php 			foreach ( $cont as $key => $val) { ?>
				<?php echo $this->makeListItem($key, $val); ?>

<?php			} ?>
			</ul>
<?php   } else {
			# allow raw HTML block to be defined by extensions
			print $cont;
		}

                $this->renderAfterPortlet( $bar );
?>
		</div>
	</div>
<?php
	}
} // end of class
