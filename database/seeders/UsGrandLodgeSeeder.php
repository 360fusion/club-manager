<?php

namespace Database\Seeders;

use App\Models\GrandLodge;
use Illuminate\Database\Seeder;

class UsGrandLodgeSeeder extends Seeder
{
    /**
     * Seed the 51 mainstream United States Grand Lodges — one per state plus
     * the District of Columbia.
     *
     * Like Australia, the United States has no national Grand Lodge. Each
     * jurisdiction is fully sovereign and recognises the others through the
     * Conference of Grand Masters of Masons in North America.
     *
     * Codes use a `usgl` prefix to avoid collisions with existing non-US
     * entries (e.g. `gli` Ireland, `glsa` South Africa, `glwa` Western
     * Australia, `glas` Switzerland).
     *
     * Founding years are well documented. Website URLs and headquarters cities
     * are best-known values and are worth spot-checking periodically.
     */
    public function run(): void
    {
        $grandLodges = [
            // New England
            ['name' => 'Grand Lodge of Connecticut AF&AM', 'code' => 'usglct', 'short_name' => 'GL Connecticut', 'website_url' => 'https://ctfreemasons.net', 'description' => 'Sovereign Grand Lodge for the State of Connecticut. Chartered 1789; headquartered in Wallingford.'],
            ['name' => 'Grand Lodge of Maine AF&AM', 'code' => 'usglme', 'short_name' => 'GL Maine', 'website_url' => 'https://mainemason.org', 'description' => 'Sovereign Grand Lodge for the State of Maine. Chartered 1820, the year Maine gained statehood; headquartered in Holden.'],
            ['name' => 'Grand Lodge of Massachusetts AF&AM', 'code' => 'usglma', 'short_name' => 'GL Massachusetts', 'website_url' => 'https://massfreemasonry.org', 'description' => 'Sovereign Grand Lodge for the Commonwealth of Massachusetts. Chartered 1733 — the oldest Grand Lodge in the Western Hemisphere; headquartered at the Grand Lodge building on Tremont Street, Boston.'],
            ['name' => 'Grand Lodge of New Hampshire F&AM', 'code' => 'usglnh', 'short_name' => 'GL New Hampshire', 'website_url' => 'https://nhgrandlodge.org', 'description' => 'Sovereign Grand Lodge for the State of New Hampshire. Chartered 1789; headquartered in Concord.'],
            ['name' => 'Grand Lodge of Rhode Island AF&AM', 'code' => 'usglri', 'short_name' => 'GL Rhode Island', 'website_url' => 'https://rimasons.org', 'description' => 'Sovereign Grand Lodge for the State of Rhode Island. Chartered 1791; headquartered in East Providence.'],
            ['name' => 'Grand Lodge of Vermont F&AM', 'code' => 'usglvt', 'short_name' => 'GL Vermont', 'website_url' => 'https://vtfreemasons.org', 'description' => 'Sovereign Grand Lodge for the State of Vermont. Chartered 1794; headquartered in Barre.'],

            // Mid-Atlantic
            ['name' => 'Grand Lodge of Delaware AF&AM', 'code' => 'usglde', 'short_name' => 'GL Delaware', 'website_url' => 'https://masonsindelaware.org', 'description' => 'Sovereign Grand Lodge for the State of Delaware. Chartered 1806; headquartered in Wilmington.'],
            ['name' => 'Grand Lodge of the District of Columbia FAAM', 'code' => 'usgldc', 'short_name' => 'GL District of Columbia', 'website_url' => 'https://dcgrandlodge.org', 'description' => 'Sovereign Grand Lodge for the District of Columbia. Chartered 1811; headquartered in Washington, D.C. Not a state jurisdiction, but sovereign and equal in standing.'],
            ['name' => 'Grand Lodge of Maryland AF&AM', 'code' => 'usglmd', 'short_name' => 'GL Maryland', 'website_url' => 'https://mdmasons.org', 'description' => 'Sovereign Grand Lodge for the State of Maryland. Chartered 1783; headquartered in Cockeysville.'],
            ['name' => 'Grand Lodge of New Jersey F&AM', 'code' => 'usglnj', 'short_name' => 'GL New Jersey', 'website_url' => 'https://newjerseygrandlodge.org', 'description' => 'Sovereign Grand Lodge for the State of New Jersey. Chartered 1786; headquartered in Burlington.'],
            ['name' => 'Grand Lodge of New York F&AM', 'code' => 'usglny', 'short_name' => 'GL New York', 'website_url' => 'https://nymasons.org', 'description' => 'Sovereign Grand Lodge for the State of New York. Chartered 1781; headquartered at Masonic Hall on West 23rd Street, New York City. One of the largest US jurisdictions.'],
            ['name' => 'Grand Lodge of Pennsylvania F&AM', 'code' => 'usglpa', 'short_name' => 'GL Pennsylvania', 'website_url' => 'https://pagrandlodge.org', 'description' => 'Sovereign Grand Lodge for the Commonwealth of Pennsylvania. Traces its origins to 1731, the earliest organised Masonic authority in America; headquartered at the Masonic Temple, Philadelphia.'],

            // South Atlantic
            ['name' => 'Grand Lodge of Florida F&AM', 'code' => 'usglfl', 'short_name' => 'GL Florida', 'website_url' => 'https://grandlodgefl.com', 'description' => 'Sovereign Grand Lodge for the State of Florida. Chartered 1830; headquartered in Jacksonville.'],
            ['name' => 'Grand Lodge of Georgia F&AM', 'code' => 'usglga', 'short_name' => 'GL Georgia', 'website_url' => 'https://glofga.org', 'description' => 'Sovereign Grand Lodge for the State of Georgia. Chartered 1786; headquartered in Macon.'],
            ['name' => 'Grand Lodge of North Carolina AF&AM', 'code' => 'usglnc', 'short_name' => 'GL North Carolina', 'website_url' => 'https://ncfreemasons.org', 'description' => 'Sovereign Grand Lodge for the State of North Carolina. Chartered 1787; headquartered in Raleigh.'],
            ['name' => 'Grand Lodge of South Carolina AFM', 'code' => 'usglsc', 'short_name' => 'GL South Carolina', 'website_url' => 'https://scgrandlodgeafm.org', 'description' => 'Sovereign Grand Lodge for the State of South Carolina. Chartered 1787; headquartered in Columbia.'],
            ['name' => 'Grand Lodge of Virginia AF&AM', 'code' => 'usglva', 'short_name' => 'GL Virginia', 'website_url' => 'https://grandlodgeofvirginia.org', 'description' => 'Sovereign Grand Lodge for the Commonwealth of Virginia. Chartered 1778; headquartered in Richmond.'],
            ['name' => 'Grand Lodge of West Virginia AF&AM', 'code' => 'usglwv', 'short_name' => 'GL West Virginia', 'website_url' => 'https://wvmasons.org', 'description' => 'Sovereign Grand Lodge for the State of West Virginia. Chartered 1865, shortly after the state separated from Virginia; headquartered in Charleston.'],

            // South Central
            ['name' => 'Grand Lodge of Alabama F&AM', 'code' => 'usglal', 'short_name' => 'GL Alabama', 'website_url' => 'https://alagl.org', 'description' => 'Sovereign Grand Lodge for the State of Alabama. Chartered 1821; headquartered in Montgomery.'],
            ['name' => 'Grand Lodge of Arkansas F&AM', 'code' => 'usglar', 'short_name' => 'GL Arkansas', 'website_url' => null, 'description' => 'Sovereign Grand Lodge for the State of Arkansas. Chartered 1838; headquartered in Little Rock.'],
            ['name' => 'Grand Lodge of Kentucky F&AM', 'code' => 'usglky', 'short_name' => 'GL Kentucky', 'website_url' => 'https://glky.org', 'description' => 'Sovereign Grand Lodge for the Commonwealth of Kentucky. Chartered 1800; headquartered in Louisville.'],
            ['name' => 'Grand Lodge of Louisiana F&AM', 'code' => 'usglla', 'short_name' => 'GL Louisiana', 'website_url' => 'https://la-mason.com', 'description' => 'Sovereign Grand Lodge for the State of Louisiana. Chartered 1812; headquartered in Alexandria.'],
            ['name' => 'Grand Lodge of Mississippi F&AM', 'code' => 'usglms', 'short_name' => 'GL Mississippi', 'website_url' => 'https://msgrandlodge.org', 'description' => 'Sovereign Grand Lodge for the State of Mississippi. Chartered 1818; headquartered in Meridian.'],
            ['name' => 'Grand Lodge of Oklahoma AF&AM', 'code' => 'usglok', 'short_name' => 'GL Oklahoma', 'website_url' => 'https://gloklahoma.com', 'description' => 'Sovereign Grand Lodge for the State of Oklahoma. Formed 1909 by the union of the Indian Territory and Oklahoma Territory Grand Lodges; headquartered in Guthrie.'],
            ['name' => 'Grand Lodge of Tennessee F&AM', 'code' => 'usgltn', 'short_name' => 'GL Tennessee', 'website_url' => 'https://grandlodge-tn.org', 'description' => 'Sovereign Grand Lodge for the State of Tennessee. Chartered 1813; headquartered in Nashville.'],
            ['name' => 'Grand Lodge of Texas AF&AM', 'code' => 'usgltx', 'short_name' => 'GL Texas', 'website_url' => 'https://grandlodgeoftexas.org', 'description' => 'Sovereign Grand Lodge for the State of Texas. Chartered 1837 during the Republic of Texas era; headquartered in Waco. One of the largest US jurisdictions by lodge count.'],

            // Midwest — East North Central
            ['name' => 'Grand Lodge of Illinois AF&AM', 'code' => 'usglil', 'short_name' => 'GL Illinois', 'website_url' => 'https://ilmason.org', 'description' => 'Sovereign Grand Lodge for the State of Illinois. Chartered 1840; headquartered in Springfield.'],
            ['name' => 'Grand Lodge of Indiana F&AM', 'code' => 'usglin', 'short_name' => 'GL Indiana', 'website_url' => 'https://indianafreemasons.com', 'description' => 'Sovereign Grand Lodge for the State of Indiana. Chartered 1818; headquartered in Indianapolis.'],
            ['name' => 'Grand Lodge of Michigan F&AM', 'code' => 'usglmi', 'short_name' => 'GL Michigan', 'website_url' => 'https://michiganmasons.org', 'description' => 'Sovereign Grand Lodge for the State of Michigan. Chartered 1826; headquartered in Grand Rapids.'],
            ['name' => 'Grand Lodge of Ohio F&AM', 'code' => 'usgloh', 'short_name' => 'GL Ohio', 'website_url' => 'https://freemason.com', 'description' => 'Sovereign Grand Lodge for the State of Ohio. Chartered 1808; headquartered in Worthington.'],
            ['name' => 'Grand Lodge of Wisconsin F&AM', 'code' => 'usglwi', 'short_name' => 'GL Wisconsin', 'website_url' => 'https://wimasons.org', 'description' => 'Sovereign Grand Lodge for the State of Wisconsin. Chartered 1843; headquartered in Dousman.'],

            // Midwest — West North Central
            ['name' => 'Grand Lodge of Iowa AF&AM', 'code' => 'usglia', 'short_name' => 'GL Iowa', 'website_url' => 'https://gl-iowa.org', 'description' => 'Sovereign Grand Lodge for the State of Iowa. Chartered 1844; headquartered in Cedar Rapids, home to one of the largest Masonic libraries in the world.'],
            ['name' => 'Grand Lodge of Kansas AF&AM', 'code' => 'usglks', 'short_name' => 'GL Kansas', 'website_url' => 'https://kansasmason.org', 'description' => 'Sovereign Grand Lodge for the State of Kansas. Chartered 1856; headquartered in Topeka.'],
            ['name' => 'Grand Lodge of Minnesota AF&AM', 'code' => 'usglmn', 'short_name' => 'GL Minnesota', 'website_url' => 'https://mnfreemasons.org', 'description' => 'Sovereign Grand Lodge for the State of Minnesota. Chartered 1853; headquartered in Bloomington.'],
            ['name' => 'Grand Lodge of Missouri AF&AM', 'code' => 'usglmo', 'short_name' => 'GL Missouri', 'website_url' => 'https://momason.org', 'description' => 'Sovereign Grand Lodge for the State of Missouri. Chartered 1821; headquartered in Columbia.'],
            ['name' => 'Grand Lodge of Nebraska AF&AM', 'code' => 'usglne', 'short_name' => 'GL Nebraska', 'website_url' => 'https://glne.org', 'description' => 'Sovereign Grand Lodge for the State of Nebraska. Chartered 1857; headquartered in Omaha.'],
            ['name' => 'Grand Lodge of North Dakota AF&AM', 'code' => 'usglnd', 'short_name' => 'GL North Dakota', 'website_url' => 'https://ndmasons.com', 'description' => 'Sovereign Grand Lodge for the State of North Dakota. Chartered 1889, the year of statehood; headquartered in Fargo.'],
            ['name' => 'Grand Lodge of South Dakota AF&AM', 'code' => 'usglsd', 'short_name' => 'GL South Dakota', 'website_url' => 'https://sdgrandlodge.org', 'description' => 'Sovereign Grand Lodge for the State of South Dakota. Traces to the 1875 Dakota Territory Grand Lodge; headquartered in Sioux Falls.'],

            // Mountain West
            ['name' => 'Grand Lodge of Arizona F&AM', 'code' => 'usglaz', 'short_name' => 'GL Arizona', 'website_url' => 'https://azmasons.org', 'description' => 'Sovereign Grand Lodge for the State of Arizona. Chartered 1882, thirty years before statehood; headquartered in Phoenix.'],
            ['name' => 'Grand Lodge of Colorado AF&AM', 'code' => 'usglco', 'short_name' => 'GL Colorado', 'website_url' => 'https://coloradofreemasons.org', 'description' => 'Sovereign Grand Lodge for the State of Colorado. Chartered 1861; headquartered in Colorado Springs.'],
            ['name' => 'Grand Lodge of Idaho AF&AM', 'code' => 'usglid', 'short_name' => 'GL Idaho', 'website_url' => 'https://idahomasons.org', 'description' => 'Sovereign Grand Lodge for the State of Idaho. Chartered 1867; headquartered in Boise.'],
            ['name' => 'Grand Lodge of Montana AF&AM', 'code' => 'usglmt', 'short_name' => 'GL Montana', 'website_url' => 'https://grandlodgemontana.org', 'description' => 'Sovereign Grand Lodge for the State of Montana. Chartered 1866 during the gold rush era; headquartered in Helena.'],
            ['name' => 'Grand Lodge of Nevada F&AM', 'code' => 'usglnv', 'short_name' => 'GL Nevada', 'website_url' => 'https://nvmasons.org', 'description' => 'Sovereign Grand Lodge for the State of Nevada. Chartered 1865; headquartered in Reno.'],
            ['name' => 'Grand Lodge of New Mexico AF&AM', 'code' => 'usglnm', 'short_name' => 'GL New Mexico', 'website_url' => 'https://nmmasons.org', 'description' => 'Sovereign Grand Lodge for the State of New Mexico. Chartered 1877; headquartered in Albuquerque.'],
            ['name' => 'Grand Lodge of Utah F&AM', 'code' => 'usglut', 'short_name' => 'GL Utah', 'website_url' => 'https://utahgrandlodge.org', 'description' => 'Sovereign Grand Lodge for the State of Utah. Chartered 1872; headquartered in Salt Lake City.'],
            ['name' => 'Grand Lodge of Wyoming AF&AM', 'code' => 'usglwy', 'short_name' => 'GL Wyoming', 'website_url' => 'https://wyomingmasons.com', 'description' => 'Sovereign Grand Lodge for the State of Wyoming. Chartered 1874; headquartered in Casper.'],

            // Pacific & Non-Contiguous
            ['name' => 'Grand Lodge of Alaska F&AM', 'code' => 'usglak', 'short_name' => 'GL Alaska', 'website_url' => 'http://alaska-mason.org', 'description' => 'Sovereign Grand Lodge for the State of Alaska. Chartered 1981, the second-youngest US jurisdiction; headquartered in Anchorage.'],
            ['name' => 'Grand Lodge of California F&AM', 'code' => 'usglca', 'short_name' => 'GL California', 'website_url' => 'https://freemason.org', 'description' => 'Sovereign Grand Lodge for the State of California. Chartered 1850, the year of statehood; headquartered at the California Masonic Memorial Temple, San Francisco.'],
            ['name' => 'Grand Lodge of Hawaii F&AM', 'code' => 'usglhi', 'short_name' => 'GL Hawaii', 'website_url' => 'https://hawaiifreemason.org', 'description' => 'Sovereign Grand Lodge for the State of Hawaii. Chartered 1989, the youngest US jurisdiction; headquartered in Honolulu.'],
            ['name' => 'Grand Lodge of Oregon AF&AM', 'code' => 'usglor', 'short_name' => 'GL Oregon', 'website_url' => 'https://oregonfreemasonry.com', 'description' => 'Sovereign Grand Lodge for the State of Oregon. Chartered 1851; headquartered in Forest Grove.'],
            ['name' => 'Grand Lodge of Washington F&AM', 'code' => 'usglwa', 'short_name' => 'GL Washington', 'website_url' => 'https://freemason-wa.org', 'description' => 'Sovereign Grand Lodge for the State of Washington. Chartered 1858; headquartered in Des Moines, Washington.'],
        ];

        foreach ($grandLodges as $grandLodge) {
            GrandLodge::updateOrCreate(
                ['code' => $grandLodge['code']],
                [...$grandLodge, 'country' => 'United States'],
            );
        }
    }
}
