/*
 * � Copyright IBM Corp. 2014
 * 
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at:
 * 
 * http://www.apache.org/licenses/LICENSE-2.0
 * 
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or
 * implied. See the License for the specific language governing
 * permissions and limitations under the License.
 */
package com.ibm.sbt.services.client.connections.activities;

import org.junit.Assert;
import org.junit.Test;

import com.ibm.commons.xml.XMLException;
import com.ibm.sbt.services.client.ClientServicesException;
import com.ibm.sbt.services.client.base.datahandlers.EntityList;

/**
 * @author mwallace
 *
 */
public class GetMyActivitiesTest extends BaseActivityServiceTest {

	@Test
	public void testGetMyActivities() throws ClientServicesException, XMLException {
		EntityList<Activity> activities = activityService.getMyActivities();
		Assert.assertNotNull("Expected non null activities", activities);
		Assert.assertFalse("Expected non empty activities", activities.isEmpty());
		for (Activity activity : activities) {
			Assert.assertNotNull("Invalid activity id", activity.getActivityUuid());
			System.out.println(activity.toXmlString());
			EntityList<ActivityNode> activityNodes = activityService.getActivityNodeDescendants(activity.getActivityUuid());
			for (ActivityNode activityNode : activityNodes) {
				Assert.assertNotNull("Invalid activity node id", activityNode.getActivityNodeUuid());
				System.err.println(activityNode.toXmlString());
			}
		}
	}
	
}
